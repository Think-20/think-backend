<?php

namespace App\Http\Services;

use Aws\S3\S3Client;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Armazenamento de arquivos local ou S3 (reutilizavel entre modulos).
 *
 * Cedente: prefixo cedente-files/{cedente_id}/{arquivo}
 * Disco padrao de novos uploads de cedente: CEDENTE_FILES_DISK=local|s3
 */
class FileStorageService
{
    public const DISK_LOCAL = 'local';

    public const DISK_S3 = 's3';

    /**
     * Disco padrao para novos arquivos de cedente.
     *
     * @return string local|s3
     */
    public static function cedenteDefaultDisk()
    {
        $disk = strtolower(trim((string) config('services.cedente_files.default_disk', self::DISK_LOCAL)));

        return $disk === self::DISK_S3 ? self::DISK_S3 : self::DISK_LOCAL;
    }

    /**
     * @return bool
     */
    public static function isS3Configured()
    {
        $cfg = config('services.s3_storage', []);

        return ! empty($cfg['key']) && ! empty($cfg['secret']) && ! empty($cfg['bucket']);
    }

    /**
     * Grava bytes no disco informado.
     *
     * @param string $key caminho relativo (ex.: cedente-files/12/abc.pdf)
     * @param string $binary
     * @param string|null $disk local|s3 (default: cedenteDefaultDisk para namespace cedente)
     * @return array{disk: string, key: string}
     * @throws Exception
     */
    public static function put($key, $binary, $disk = null)
    {
        $disk = self::normalizeDisk($disk ?: self::cedenteDefaultDisk());
        $key = self::normalizeKey($key);

        if ($disk === self::DISK_S3) {
            self::s3Put($key, $binary);

            return ['disk' => self::DISK_S3, 'key' => $key];
        }

        self::localPut($key, $binary);

        return ['disk' => self::DISK_LOCAL, 'key' => $key];
    }

    /**
     * Le conteudo binario.
     *
     * @param string $key
     * @param string $disk
     * @return string
     * @throws Exception
     */
    public static function get($key, $disk = self::DISK_LOCAL)
    {
        $disk = self::normalizeDisk($disk);
        $key = self::normalizeKey($key);

        if ($disk === self::DISK_S3) {
            return self::s3Get($key);
        }

        return self::localGet($key);
    }

    /**
     * @param string $key
     * @param string $disk
     * @return bool
     */
    public static function exists($key, $disk = self::DISK_LOCAL)
    {
        try {
            $disk = self::normalizeDisk($disk);
            $key = self::normalizeKey($key);

            if ($disk === self::DISK_S3) {
                return self::s3Exists($key);
            }

            return is_file(self::localAbsolutePath($key));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string $key
     * @param string $disk
     * @return bool
     */
    public static function delete($key, $disk = self::DISK_LOCAL)
    {
        $disk = self::normalizeDisk($disk);
        $key = self::normalizeKey($key);

        if ($disk === self::DISK_S3) {
            return self::s3Delete($key);
        }

        $path = self::localAbsolutePath($key);
        if (is_file($path)) {
            return @unlink($path);
        }

        return false;
    }

    /**
     * Chave S3/local padrao para arquivo de cedente.
     *
     * @param int $cedenteId
     * @param string $storedName
     * @return string
     */
    public static function cedenteStorageKey($cedenteId, $storedName)
    {
        return 'cedente-files/' . (int) $cedenteId . '/' . ltrim((string) $storedName, '/');
    }

    /**
     * Chave legada (arquivos antigos em disco plano cedente-files/{name}).
     *
     * @param string $storedName
     * @return string
     */
    public static function cedenteLegacyLocalKey($storedName)
    {
        return 'cedente-files/' . ltrim((string) $storedName, '/');
    }

    /**
     * Raiz local: FILES_FOLDER ou storage/app/files.
     *
     * @return string
     */
    public static function localRoot()
    {
        $base = env('FILES_FOLDER');
        if ($base === null || trim((string) $base) === '') {
            return storage_path('app' . DIRECTORY_SEPARATOR . 'files');
        }

        return rtrim((string) $base, '/\\');
    }

    /**
     * @param string $key
     * @return string
     */
    public static function localAbsolutePath($key)
    {
        $key = self::normalizeKey($key);

        return self::localRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
    }

    /**
     * @param string|null $disk
     * @return string
     */
    private static function normalizeDisk($disk)
    {
        $disk = strtolower(trim((string) $disk));
        if ($disk === self::DISK_S3) {
            if (! self::isS3Configured()) {
                throw new InvalidArgumentException('S3 nao configurado (AWS_KEY/S3_KEY, AWS_SECRET/S3_SECRET, AWS_BUCKET/S3_BUCKET_NAME)');
            }

            return self::DISK_S3;
        }

        return self::DISK_LOCAL;
    }

    /**
     * @param string $key
     * @return string
     */
    private static function normalizeKey($key)
    {
        $key = trim(str_replace('\\', '/', (string) $key), '/');
        if ($key === '') {
            throw new InvalidArgumentException('storage key invalida');
        }

        return $key;
    }

    /**
     * @return S3Client
     */
    private static function s3Client()
    {
        $cfg = config('services.s3_storage', []);

        return new S3Client([
            'version' => 'latest',
            'region' => isset($cfg['region']) ? $cfg['region'] : 'us-east-1',
            'credentials' => [
                'key' => $cfg['key'],
                'secret' => $cfg['secret'],
            ],
        ]);
    }

    /**
     * @return string
     */
    private static function s3Bucket()
    {
        return (string) config('services.s3_storage.bucket');
    }

    /**
     * @param string $key
     * @param string $binary
     */
    private static function s3Put($key, $binary)
    {
        $fullKey = self::s3ObjectKey($key);

        try {
            self::s3Client()->putObject([
                'Bucket' => self::s3Bucket(),
                'Key' => $fullKey,
                'Body' => $binary,
            ]);
        } catch (\Exception $e) {
            Log::warning('FileStorageService: falha S3 put', ['key' => $fullKey, 'message' => $e->getMessage()]);
            throw new Exception('Falha ao gravar arquivo no S3: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param string $key
     * @return string
     */
    private static function s3Get($key)
    {
        $fullKey = self::s3ObjectKey($key);

        try {
            $result = self::s3Client()->getObject([
                'Bucket' => self::s3Bucket(),
                'Key' => $fullKey,
            ]);

            return (string) $result['Body'];
        } catch (\Exception $e) {
            throw new Exception('Arquivo nao encontrado no S3: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function s3Exists($key)
    {
        $fullKey = self::s3ObjectKey($key);

        return self::s3Client()->doesObjectExist(self::s3Bucket(), $fullKey);
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function s3Delete($key)
    {
        $fullKey = self::s3ObjectKey($key);

        try {
            self::s3Client()->deleteObject([
                'Bucket' => self::s3Bucket(),
                'Key' => $fullKey,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::warning('FileStorageService: falha S3 delete', ['key' => $fullKey, 'message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @param string $key
     * @return string
     */
    private static function s3ObjectKey($key)
    {
        $prefix = trim((string) config('services.s3_storage.prefix', ''), '/');
        $key = ltrim($key, '/');

        return $prefix !== '' ? $prefix . '/' . $key : $key;
    }

    /**
     * @param string $key
     * @param string $binary
     */
    private static function localPut($key, $binary)
    {
        $path = self::localAbsolutePath($key);
        $dir = dirname($path);

        if (! is_dir($dir)) {
            try {
                mkdir($dir, 0755, true);
            } catch (\Exception $e) {
                @shell_exec('sudo mkdir -p ' . escapeshellarg($dir));
            }
        }

        if (! is_dir($dir)) {
            throw new Exception('Nao foi possivel criar diretorio local: ' . $dir);
        }

        if (file_put_contents($path, $binary) === false) {
            throw new Exception('Falha ao gravar arquivo no disco local');
        }
    }

    /**
     * @param string $key
     * @return string
     */
    private static function localGet($key)
    {
        $path = self::localAbsolutePath($key);
        if (! is_file($path)) {
            throw new Exception('Arquivo fisico nao encontrado');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new Exception('Falha ao ler arquivo local');
        }

        return $content;
    }
}
