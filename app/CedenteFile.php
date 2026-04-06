<?php

namespace App;

use Exception;
use Illuminate\Database\Eloquent\Model;

class CedenteFile extends Model
{
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
    ];

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

    public static function storageDir()
    {
        $base = env('FILES_FOLDER');
        if (empty($base)) {
            throw new Exception('FILES_FOLDER nao configurado no .env');
        }

        return rtrim($base, '/') . '/cedente-files';
    }

    public function absolutePath()
    {
        return self::storageDir() . '/' . $this->name;
    }

    public function deletePhysicalFile()
    {
        $path = $this->absolutePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Grava bytes no disco e persiste o registro (transação deve envolver o chamador).
     *
     * @param string $binary
     * @return static
     */
    public static function storeFromBinary($cedenteId, $documentType, $originalName, $binary, $extension = null)
    {
        $dir = self::storageDir();
        if (! is_dir($dir)) {
            try {
                mkdir($dir, 0755, true);
            } catch (Exception $e) {
                @shell_exec('sudo mkdir -p ' . escapeshellarg($dir));
            }
        }

        if (! is_dir($dir)) {
            throw new Exception('Nao foi possivel criar o diretorio de arquivos do cedente');
        }

        $ext = $extension;
        if ($ext === null || $ext === '') {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        }
        if ($ext === null || $ext === '') {
            $ext = 'bin';
        }

        $storedName = sha1($cedenteId . $documentType . microtime(true) . mt_rand()) . '.' . $ext;

        $fullPath = $dir . '/' . $storedName;
        if (file_put_contents($fullPath, $binary) === false) {
            throw new Exception('Falha ao gravar arquivo no disco');
        }

        $row = new self([
            'cedente_id' => $cedenteId,
            'name' => $storedName,
            'original_name' => $originalName,
            'type' => $ext,
            'document_type' => $documentType,
        ]);
        $row->save();

        return $row;
    }
}
