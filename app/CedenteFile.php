<?php

namespace App;

use App\Http\Services\FileStorageService;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CedenteFile extends Model
{
    use SoftDeletes;

    protected $table = 'cedente_file';

    public const DOC_CONTRATO_ESTATUTO_SOCIAL = 1;
    public const DOC_CERTIDAO_SIMPLIFICADA_JUNTA = 2;
    public const DOC_ATA_ELEICAO_DIRETORIA = 3;
    public const DOC_CARTAO_CNPJ = 4;
    public const DOC_PARECER_COMPLIANCE_REPUTACIONAL = 5;
    public const DOC_DEMONSTRACAO_FINANCEIRA = 6;
    public const DOC_ATA_PARECER_CREDITO = 7;
    public const DOC_CONSULTA_ORGAOS_PROTECAO_CREDITO = 8;
    public const DOC_RELATORIO_VISITA = 9;
    public const DOC_PARECER_FICHA_GESTOR = 10;
    public const DOC_COMPROVANTE_RESIDENCIA = 11;
    public const DOC_DECLARACAO_FATURAMENTO = 12;
    public const DOC_DECLARACAO_RELACIONAMENTO_BANCARIO = 13;

    protected $fillable = [
        'cedente_id',
        'name',
        'original_name',
        'type',
        'document_type',
        'valido',
        'storage_disk',
        'storage_key',
    ];

    protected $casts = [
        'valido' => 'boolean',
    ];

    protected $dates = [
        'deleted_at',
    ];

    public const STATUS_EM_AVALIACAO = 'em_avaliacao';

    public const STATUS_VALIDO = 'valido';

    /**
     * @param bool $valido
     * @return string
     */
    public static function statusFromValido($valido)
    {
        return $valido ? self::STATUS_VALIDO : self::STATUS_EM_AVALIACAO;
    }

    /**
     * Campo em cedente_inconsistencia para documento recusado/ausente.
     *
     * @param int $documentType
     * @return string
     */
    public static function inconsistenciaCampoForDocumentType($documentType)
    {
        return 'arquivo.document_type.' . (int) $documentType;
    }

    public static function documentTypeLabels()
    {
        return [
            self::DOC_CONTRATO_ESTATUTO_SOCIAL => 'Contrato/Estatuto Social',
            self::DOC_CERTIDAO_SIMPLIFICADA_JUNTA => 'Certidão Simplificada da Junta Comercial',
            self::DOC_ATA_ELEICAO_DIRETORIA => 'Ata de Eleição da Diretoria',
            self::DOC_CARTAO_CNPJ => 'Cartão CNPJ',
            self::DOC_PARECER_COMPLIANCE_REPUTACIONAL => 'Parecer de Compliance/Reputacional',
            self::DOC_DEMONSTRACAO_FINANCEIRA => 'Demonstração Financeira',
            self::DOC_ATA_PARECER_CREDITO => 'Ata/Parecer de Crédito',
            self::DOC_CONSULTA_ORGAOS_PROTECAO_CREDITO => 'Consulta aos Órgãos de Proteção de Crédito',
            self::DOC_RELATORIO_VISITA => 'Relatório de Visita',
            self::DOC_PARECER_FICHA_GESTOR => 'Parecer/Ficha do Gestor',
            self::DOC_COMPROVANTE_RESIDENCIA => 'Comprovante de Residência',
            self::DOC_DECLARACAO_FATURAMENTO => 'Declaração de Faturamento',
            self::DOC_DECLARACAO_RELACIONAMENTO_BANCARIO => 'Declaração de Relacionamento Bancário',
        ];
    }

    public static function requiredDocumentTypeIds()
    {
        return range(1, 13);
    }

    public function cedente()
    {
        return $this->belongsTo(Cedente::class);
    }

    /**
     * Raiz dos uploads legados: FILES_FOLDER/cedente-files (plano).
     */
    public static function storageDir()
    {
        return FileStorageService::localRoot() . DIRECTORY_SEPARATOR . 'cedente-files';
    }

    /**
     * Disco efetivo do registro (local ou s3).
     *
     * @return string
     */
    public function resolvedStorageDisk()
    {
        $disk = isset($this->storage_disk) ? strtolower(trim((string) $this->storage_disk)) : '';

        return $disk === FileStorageService::DISK_S3 ? FileStorageService::DISK_S3 : FileStorageService::DISK_LOCAL;
    }

    /**
     * Chave relativa para FileStorageService.
     *
     * @return string
     */
    public function resolvedStorageKey()
    {
        if (! empty($this->storage_key)) {
            return (string) $this->storage_key;
        }

        return FileStorageService::cedenteLegacyLocalKey($this->name);
    }

    public function absolutePath()
    {
        return FileStorageService::localAbsolutePath($this->resolvedStorageKey());
    }

    /**
     * Conteudo binario (local legado ou S3).
     *
     * @return string
     * @throws Exception
     */
    public function readBinary()
    {
        return FileStorageService::get($this->resolvedStorageKey(), $this->resolvedStorageDisk());
    }

    /**
     * @param int $id cedente_file.id
     * @return string conteudo binario
     */
    public static function readBinaryById($id)
    {
        $file = static::find((int) $id);
        if (! $file) {
            throw new Exception('O arquivo solicitado nao existe.');
        }

        return $file->readBinary();
    }

    /**
     * Caminho absoluto local (compat download legado) ou temp file para S3.
     *
     * @param int $id cedente_file.id
     * @return string
     */
    public static function downloadFile($id)
    {
        $file = static::find((int) $id);
        if (! $file) {
            throw new Exception('O arquivo solicitado nao existe.');
        }

        if ($file->resolvedStorageDisk() === FileStorageService::DISK_LOCAL) {
            $path = $file->absolutePath();
            if (! is_file($path)) {
                throw new Exception('Arquivo fisico nao encontrado.');
            }

            return $path;
        }

        $binary = $file->readBinary();
        $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cedente-file-' . (int) $id . '-' . uniqid('', true);
        if (file_put_contents($temp, $binary) === false) {
            throw new Exception('Falha ao preparar download temporario.');
        }

        return $temp;
    }

    /**
     * Gera ZIP com todos os arquivos ativos (nao soft-deleted) do cedente.
     *
     * @param int $cedenteId
     * @return string caminho absoluto do zip temporario
     */
    public static function downloadAllFiles($cedenteId)
    {
        $files = static::where('cedente_id', (int) $cedenteId)->get();
        if ($files->isEmpty()) {
            throw new Exception('Nenhum arquivo encontrado para este cedente.');
        }

        $zip = new \ZipArchive();
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cedente-' . (int) $cedenteId . '-' . uniqid('', true) . '.zip';

        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Erro ao criar o arquivo zip.');
        }

        $usedNames = [];
        $added = 0;

        foreach ($files as $file) {
            try {
                $binary = $file->readBinary();
            } catch (\Exception $e) {
                continue;
            }

            $entryName = $file->original_name ?: $file->name;
            if (isset($usedNames[$entryName])) {
                $usedNames[$entryName]++;
                $pathInfo = pathinfo($entryName);
                $base = isset($pathInfo['filename']) ? $pathInfo['filename'] : $entryName;
                $ext = isset($pathInfo['extension']) && $pathInfo['extension'] !== ''
                    ? '.' . $pathInfo['extension']
                    : '';
                $entryName = $base . '_' . $usedNames[$entryName] . $ext;
            } else {
                $usedNames[$entryName] = 0;
            }

            $labels = self::documentTypeLabels();
            $label = isset($labels[$file->document_type])
                ? $labels[$file->document_type]
                : ('tipo_' . $file->document_type);
            $safeLabel = preg_replace('/[^a-zA-Z0-9_\- ]+/', '', $label);
            $zipPath = trim($safeLabel) . '/' . $entryName;

            $zip->addFromString($zipPath, $binary);
            $added++;
        }

        $zip->close();

        if ($added < 1) {
            @unlink($path);
            throw new Exception('Nenhum arquivo fisico encontrado para este cedente.');
        }

        return $path;
    }

    public function deletePhysicalFile()
    {
        FileStorageService::delete($this->resolvedStorageKey(), $this->resolvedStorageDisk());
    }

    /**
     * Grava bytes e persiste o registro (transacao deve envolver o chamador).
     * Novos uploads usam CEDENTE_FILES_DISK (local ou s3).
     *
     * @param string $binary
     * @return static
     */
    public static function storeFromBinary($cedenteId, $documentType, $originalName, $binary, $extension = null)
    {
        $ext = $extension;
        if ($ext === null || $ext === '') {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        }
        if ($ext === null || $ext === '') {
            $ext = 'bin';
        }

        $storedName = sha1($cedenteId . $documentType . microtime(true) . mt_rand()) . '.' . $ext;
        $storageKey = FileStorageService::cedenteStorageKey($cedenteId, $storedName);
        $disk = FileStorageService::cedenteDefaultDisk();

        $stored = FileStorageService::put($storageKey, $binary, $disk);

        $row = new self([
            'cedente_id' => $cedenteId,
            'name' => $storedName,
            'original_name' => $originalName,
            'type' => $ext,
            'document_type' => $documentType,
            'valido' => false,
            'storage_disk' => $stored['disk'],
            'storage_key' => $stored['key'],
        ]);
        $row->save();

        return $row;
    }
}
