<?php

namespace App;

use App\Http\Services\FileStorageService;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CotistaFile extends Model
{
    use SoftDeletes;

    protected $table = 'cotista_file';

    public const DOC_IDENTIFICACAO = 1;
    public const DOC_COMPROVANTE_ENDERECO = 2;

    protected $fillable = [
        'cotista_id',
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
            self::DOC_IDENTIFICACAO => 'Documento de Identificacao',
            self::DOC_COMPROVANTE_ENDERECO => 'Comprovante de Endereco',
        ];
    }

    public static function documentTypeIds()
    {
        return [
            self::DOC_IDENTIFICACAO,
            self::DOC_COMPROVANTE_ENDERECO,
        ];
    }

    public function cotista()
    {
        return $this->belongsTo(Cotista::class);
    }

    /**
     * @return string
     */
    public function resolvedStorageDisk()
    {
        $disk = isset($this->storage_disk) ? strtolower(trim((string) $this->storage_disk)) : '';

        return $disk === FileStorageService::DISK_S3 ? FileStorageService::DISK_S3 : FileStorageService::DISK_LOCAL;
    }

    /**
     * @return string
     */
    public function resolvedStorageKey()
    {
        if (! empty($this->storage_key)) {
            return (string) $this->storage_key;
        }

        return FileStorageService::cotistaStorageKey($this->cotista_id, $this->name);
    }

    public function absolutePath()
    {
        return FileStorageService::localAbsolutePath($this->resolvedStorageKey());
    }

    /**
     * @return string
     * @throws Exception
     */
    public function readBinary()
    {
        return FileStorageService::get($this->resolvedStorageKey(), $this->resolvedStorageDisk());
    }

    /**
     * @param int $id
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
        $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cotista-file-' . (int) $id . '-' . uniqid('', true);
        if (file_put_contents($temp, $binary) === false) {
            throw new Exception('Falha ao preparar download temporario.');
        }

        return $temp;
    }

    /**
     * @param int $cotistaId
     * @return string
     */
    public static function downloadAllFiles($cotistaId)
    {
        $files = static::where('cotista_id', (int) $cotistaId)->get();
        if ($files->isEmpty()) {
            throw new Exception('Nenhum arquivo encontrado para este cotista.');
        }

        $zip = new \ZipArchive();
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cotista-' . (int) $cotistaId . '-' . uniqid('', true) . '.zip';

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
            throw new Exception('Nenhum arquivo fisico encontrado para este cotista.');
        }

        return $path;
    }

    public function deletePhysicalFile()
    {
        FileStorageService::delete($this->resolvedStorageKey(), $this->resolvedStorageDisk());
    }

    /**
     * @param string $binary
     * @return static
     */
    public static function storeFromBinary($cotistaId, $documentType, $originalName, $binary, $extension = null)
    {
        $ext = $extension;
        if ($ext === null || $ext === '') {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        }
        if ($ext === null || $ext === '') {
            $ext = 'bin';
        }

        $storedName = sha1($cotistaId . $documentType . microtime(true) . mt_rand()) . '.' . $ext;
        $storageKey = FileStorageService::cotistaStorageKey($cotistaId, $storedName);
        $disk = FileStorageService::cotistaDefaultDisk();

        $stored = FileStorageService::put($storageKey, $binary, $disk);

        $row = new self([
            'cotista_id' => $cotistaId,
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
