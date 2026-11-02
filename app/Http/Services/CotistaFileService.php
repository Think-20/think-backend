<?php

namespace App\Http\Services;

use App\Cotista;
use App\CotistaAudit;
use App\CotistaFile;
use App\CotistaInconsistencia;
use App\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CotistaFileService
{
    /**
     * Aprova ou recusa um arquivo do cotista.
     *
     * @param array $data id (cotista_file), valido, motivo
     * @return Cotista
     */
    public static function setValidacao(array $data)
    {
        $data = CotistaService::normalizePayload($data);

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

        $fileId = (int) $data['id'];

        return DB::transaction(function () use ($fileId, $valido, $data) {
            $file = CotistaFile::with('cotista')->find($fileId);
            if (! $file || $file->cotista_id === null) {
                throw new InvalidArgumentException('Arquivo do cotista nao encontrado');
            }

            $cotista = CotistaService::findCotista($file->cotista_id);
            CotistaPermissionService::assertCanValidarArquivo($cotista);

            if ($valido) {
                return self::aprovarArquivo($file);
            }

            $motivo = isset($data['motivo']) && is_string($data['motivo']) ? trim($data['motivo']) : '';

            return self::recusarArquivo($file, $motivo);
        });
    }

    /**
     * @param CotistaFile $file
     * @return Cotista
     */
    private static function aprovarArquivo(CotistaFile $file)
    {
        $file->valido = true;
        $file->save();

        $cotista = $file->cotista;
        $labels = CotistaFile::documentTypeLabels();
        $label = isset($labels[$file->document_type]) ? $labels[$file->document_type] : 'document_type ' . $file->document_type;

        self::recordArquivoAudit(
            $cotista->id,
            CotistaAudit::EVENT_ARQUIVO_VALIDADO,
            $cotista->status ?: Cotista::STATUS_PENDENTE,
            [
                'descricao' => 'Arquivo validado: ' . $label,
                'arquivo_id' => $file->id,
                'document_type' => $file->document_type,
            ]
        );

        return $cotista->fresh(Cotista::apiRelations());
    }

    /**
     * @param CotistaFile $file
     * @param string $motivo
     * @return Cotista
     */
    private static function recusarArquivo(CotistaFile $file, $motivo = '')
    {
        $cotista = $file->cotista;
        $statusAntes = $cotista->status ?: Cotista::STATUS_PENDENTE;
        $documentType = (int) $file->document_type;
        $labels = CotistaFile::documentTypeLabels();
        $label = isset($labels[$documentType]) ? $labels[$documentType] : 'document_type ' . $documentType;
        $campo = CotistaFile::inconsistenciaCampoForDocumentType($documentType);
        $valorInconsistencia = $motivo !== '' ? $motivo : $label . ' recusado';
        $arquivoId = $file->id;
        $storedName = $file->name;
        $originalName = $file->original_name;

        $file->delete();

        CotistaInconsistencia::updateOrCreate(
            [
                'cotista_id' => $cotista->id,
                'campo_inconsistente' => $campo,
            ],
            [
                'valor_serpro' => $valorInconsistencia,
            ]
        );

        $cotista->unsetRelation('inconsistencias');

        if ($cotista->status !== Cotista::STATUS_INCONSISTENTE) {
            $cotista->status = Cotista::STATUS_INCONSISTENTE;
            $cotista->save();

            self::recordArquivoAudit(
                $cotista->id,
                CotistaAudit::EVENT_STATUS_ALTERADO,
                Cotista::STATUS_INCONSISTENTE,
                [
                    'descricao' => 'Status alterado de ' . $statusAntes . ' para inconsistente',
                    'motivo' => 'arquivo_recusado',
                    'document_type' => $documentType,
                ],
                $statusAntes
            );
        }

        self::recordArquivoAudit(
            $cotista->id,
            CotistaAudit::EVENT_ARQUIVO_RECUSADO,
            Cotista::STATUS_INCONSISTENTE,
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

        return $cotista->fresh(Cotista::apiRelations());
    }

    /**
     * @param int $cotistaId
     * @param string $event
     * @param string $newStatus
     * @param array $changes
     * @param string|null $oldStatus
     */
    private static function recordArquivoAudit($cotistaId, $event, $newStatus, array $changes, $oldStatus = null)
    {
        $user = User::logged();

        CotistaAudit::create([
            'cotista_id' => (int) $cotistaId,
            'user_id' => $user ? (int) $user->id : null,
            'event' => $event,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changes' => $changes,
        ]);
    }
}
