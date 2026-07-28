<?php

namespace App\Http\Services;

use App\Cedente;
use App\CedenteAudit;
use App\CedenteFile;
use App\CedenteInconsistencia;
use App\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CedenteFileService
{
    /**
     * Aprova ou recusa um arquivo do cedente.
     *
     * @param array $data fund_id, id (cedente_file), valido (bool), motivo (opcional ao recusar)
     * @return Cedente
     */
    public static function setValidacao(array $data)
    {
        $data = CedenteService::normalizePayload($data);

        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID do arquivo e obrigatorio');
        }

        if (! array_key_exists('valido', $data)) {
            throw new InvalidArgumentException('Campo valido e obrigatorio (true ou false)');
        }

        $valido = filter_var($data['valido'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($valido === null) {
            throw new InvalidArgumentException('Campo valido deve ser true ou false');
        }

        $fundId = CedenteService::resolveFundId($data);
        $fileId = (int) $data['id'];

        return DB::transaction(function () use ($fundId, $fileId, $valido, $data) {
            $file = CedenteFile::with('cedente')->find($fileId);
            if (! $file || $file->cedente_id === null) {
                throw new InvalidArgumentException('Arquivo do cedente nao encontrado');
            }

            $cedente = CedenteService::findCedenteForFund($file->cedente_id, $fundId);
            CedentePermissionService::assertCanValidarArquivo($cedente);

            if ($valido) {
                return self::aprovarArquivo($file);
            }

            $motivo = isset($data['motivo']) && is_string($data['motivo']) ? trim($data['motivo']) : '';

            return self::recusarArquivo($file, $motivo);
        });
    }

    /**
     * @param CedenteFile $file
     * @return Cedente
     */
    private static function aprovarArquivo(CedenteFile $file)
    {
        $file->valido = true;
        $file->save();

        $cedente = $file->cedente;
        $labels = CedenteFile::documentTypeLabels();
        $label = isset($labels[$file->document_type]) ? $labels[$file->document_type] : 'document_type ' . $file->document_type;

        self::recordArquivoAudit(
            $cedente->id,
            CedenteAudit::EVENT_ARQUIVO_VALIDADO,
            $cedente->status ?: Cedente::STATUS_PENDENTE,
            [
                'descricao' => 'Arquivo validado: ' . $label,
                'arquivo_id' => $file->id,
                'document_type' => $file->document_type,
            ]
        );

        return $cedente->fresh(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'cedenteFiles', 'inconsistencias']);
    }

    /**
     * @param CedenteFile $file
     * @param string $motivo
     * @return Cedente
     */
    private static function recusarArquivo(CedenteFile $file, $motivo = '')
    {
        $cedente = $file->cedente;
        $statusAntes = $cedente->status ?: Cedente::STATUS_PENDENTE;
        $documentType = (int) $file->document_type;
        $labels = CedenteFile::documentTypeLabels();
        $label = isset($labels[$documentType]) ? $labels[$documentType] : 'document_type ' . $documentType;
        $campo = CedenteFile::inconsistenciaCampoForDocumentType($documentType);
        $valorInconsistencia = $motivo !== '' ? $motivo : $label . ' recusado';
        $arquivoId = $file->id;
        $storedName = $file->name;
        $originalName = $file->original_name;

        $file->delete();

        CedenteInconsistencia::updateOrCreate(
            [
                'cedente_id' => $cedente->id,
                'campo_inconsistente' => $campo,
            ],
            [
                'valor_serpro' => $valorInconsistencia,
            ]
        );

        $cedente->unsetRelation('inconsistencias');

        if ($cedente->status !== Cedente::STATUS_INCONSISTENTE) {
            $cedente->status = Cedente::STATUS_INCONSISTENTE;
            $cedente->save();

            self::recordArquivoAudit(
                $cedente->id,
                CedenteAudit::EVENT_STATUS_ALTERADO,
                Cedente::STATUS_INCONSISTENTE,
                [
                    'descricao' => 'Status alterado de ' . $statusAntes . ' para inconsistente',
                    'motivo' => 'arquivo_recusado',
                    'document_type' => $documentType,
                ],
                $statusAntes
            );
        }

        self::recordArquivoAudit(
            $cedente->id,
            CedenteAudit::EVENT_ARQUIVO_RECUSADO,
            Cedente::STATUS_INCONSISTENTE,
            [
                'descricao' => 'Arquivo recusado: ' . $label,
                'arquivo_id' => $arquivoId,
                'document_type' => $documentType,
                'name' => $storedName,
                'original_name' => $originalName,
                'motivo' => $valorInconsistencia,
                'soft_deleted' => true,
            ],
            $statusAntes
        );

        return $cedente->fresh(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'cedenteFiles', 'inconsistencias']);
    }

    /**
     * @param int $cedenteId
     * @param string $event
     * @param string $newStatus
     * @param array $changes
     * @param string|null $oldStatus
     */
    private static function recordArquivoAudit($cedenteId, $event, $newStatus, array $changes, $oldStatus = null)
    {
        $user = User::logged();

        CedenteAudit::create([
            'cedente_id' => (int) $cedenteId,
            'user_id' => $user ? (int) $user->id : null,
            'event' => $event,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changes' => $changes,
        ]);
    }
}
