<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cotista extends Model
{
    public const STATUS_RASCUNHO = 'rascunho';
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_EM_AVALIACAO = 'em_avaliacao';
    /** Inconsistencia cadastral (correcoes do aprovador / arquivo). Preenchimento corrige. */
    public const STATUS_INCONSISTENTE = 'inconsistente';
    /** Alias semantico de STATUS_INCONSISTENTE. */
    public const STATUS_INCONSISTENCIA_CADASTRAL = 'inconsistente';
    /** Reservado para restricao futura (Vadu). Nada grava este valor nesta versao. */
    public const STATUS_INCONSISTENCIA_JURIDICA = 'inconsistencia_juridica';
    public const STATUS_APROVADO = 'aprovado';
    public const STATUS_SOLICITAR_CORRECOES = 'solicitar_correcoes';
    public const STATUS_REJEITADO = 'rejeitado';
    public const STATUS_VENCIDO = 'vencido';
    public const STATUS_CANCELADO = 'cancelado';

    public const SLA_DIAS_UTEIS_DEFAULT = 5;
    public const SLA_MESES_APROVADO_DEFAULT = 3;

    public const AVALIACAO_APROVADO = 'aprovado';
    public const AVALIACAO_SOLICITAR_CORRECOES = 'solicitar_correcoes';
    public const AVALIACAO_REJEITADO = 'rejeitado';

    public const CORRESPONDENCIA_RESIDENCIAL = 'residencial';
    public const CORRESPONDENCIA_COMERCIAL = 'comercial';

    public const SITUACAO_ISENTO = 'isento';
    public const SITUACAO_NAO_ISENTO = 'nao_isento';

    public const TIPO_PESSOA_FISICA = 'fisica';
    public const TIPO_PESSOA_JURIDICA = 'juridica';

    protected $table = 'cotista';

    protected $fillable = [
        'nome_completo',
        'tipo_pessoa',
        'documento',
        'tin',
        'nome_mae',
        'sexo',
        'local_nascimento',
        'data_nascimento',
        'estado_civil',
        'regime_casamento',
        'nome_conjuge',
        'cpf_conjuge',
        'numero_dependentes',
        'nacionalidade',
        'possui_outra_nacionalidade',
        'outras_nacionalidades',
        'tipo_documento',
        'numero_documento',
        'data_emissao_documento',
        'orgao_emissor',
        'uf_pais_emissor',
        'endereco_residencial_id',
        'endereco_comercial_id',
        'tempo_residencia',
        'endereco_correspondencia',
        'autorizacao_residencia_outros_paises',
        'paises_residencia',
        'possui_outro_domicilio_fiscal',
        'outros_domicilios_fiscais',
        'telefone',
        'celular',
        'email',
        'us_person',
        'natureza_ocupacao',
        'instituicao_trabalho',
        'cnpj_empregador',
        'telefone_comercial',
        'profissao',
        'cargo',
        'email_comercial',
        'codigo_operacional_cvm',
        'rde_cademp',
        'pais_origem',
        'representante_brasil',
        'representante_coresponsavel',
        'custodiante',
        'administrador_carteira',
        'situacao',
        'proposito_cambio',
        'proposito_aplicacoes',
        'proposito_intermediacao',
        'proposito_credito',
        'proposito_acessorias',
        'proposito_outros',
        'e_ppe',
        'ppe_cargo',
        'relacionamento_ppe',
        'ppe_relacionada_cargo',
        'ppe_relacionada_nome',
        'ppe_relacionada_cpf',
        'ppe_relacionada_nacionalidade',
        'status',
        'sla',
        'prazo_atualizacao_cadastral',
    ];

    protected $casts = [
        'possui_outra_nacionalidade' => 'boolean',
        'autorizacao_residencia_outros_paises' => 'boolean',
        'possui_outro_domicilio_fiscal' => 'boolean',
        'us_person' => 'boolean',
        'proposito_cambio' => 'boolean',
        'proposito_aplicacoes' => 'boolean',
        'proposito_intermediacao' => 'boolean',
        'proposito_credito' => 'boolean',
        'proposito_acessorias' => 'boolean',
        'e_ppe' => 'boolean',
        'relacionamento_ppe' => 'boolean',
    ];

    protected $dates = [
        'data_nascimento',
        'data_emissao_documento',
        'sla',
    ];

    /**
     * @return string[]
     */
    public static function avaliacaoResultadoValues()
    {
        return [
            self::AVALIACAO_APROVADO,
            self::AVALIACAO_SOLICITAR_CORRECOES,
            self::AVALIACAO_REJEITADO,
        ];
    }

    /**
     * @return string[]
     */
    public static function correspondenciaValues()
    {
        return [
            self::CORRESPONDENCIA_RESIDENCIAL,
            self::CORRESPONDENCIA_COMERCIAL,
        ];
    }

    /**
     * @return string[]
     */
    public static function situacaoValues()
    {
        return [
            self::SITUACAO_ISENTO,
            self::SITUACAO_NAO_ISENTO,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels()
    {
        return [
            self::STATUS_RASCUNHO => 'Rascunho',
            self::STATUS_PENDENTE => 'Pendente',
            self::STATUS_EM_AVALIACAO => 'Em avaliacao',
            self::STATUS_INCONSISTENTE => 'Inconsistencia Cadastral',
            self::STATUS_INCONSISTENCIA_JURIDICA => 'Inconsistencia Juridica',
            self::STATUS_APROVADO => 'Aprovado',
            self::STATUS_REJEITADO => 'Rejeitado',
            self::STATUS_VENCIDO => 'Vencido',
            self::STATUS_CANCELADO => 'Cancelado',
            self::STATUS_SOLICITAR_CORRECOES => 'Inconsistencia Cadastral',
        ];
    }

    /**
     * @param string|null $status
     * @return string
     */
    public static function statusLabel($status)
    {
        $status = $status ?: self::STATUS_PENDENTE;
        $labels = self::statusLabels();

        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * Mesma regra e env de dias uteis do cedente (`CEDENTE_SLA_DIAS_UTEIS`, padrao 5).
     *
     * @return int
     */
    public static function slaBusinessDaysCount()
    {
        return Cedente::slaBusinessDaysCount();
    }

    /**
     * @return string[]
     */
    public static function slaSkippedStatuses()
    {
        return [
            self::STATUS_RASCUNHO,
            self::STATUS_INCONSISTENTE,
            self::STATUS_INCONSISTENCIA_JURIDICA,
            self::STATUS_CANCELADO,
            self::STATUS_SOLICITAR_CORRECOES,
            self::STATUS_REJEITADO,
            self::STATUS_VENCIDO,
        ];
    }

    public static function shouldRecalculateSlaForStatus($status)
    {
        $status = $status ?: self::STATUS_PENDENTE;

        return ! in_array($status, self::slaSkippedStatuses(), true);
    }

    /**
     * @param \DateTimeInterface|string|null $fromDate
     * @return string Y-m-d
     */
    public static function computeSlaDeadline($fromDate = null)
    {
        if ($fromDate === null) {
            $fromDate = date('Y-m-d');
        }

        return BrazilHoliday::addBusinessDays($fromDate, self::slaBusinessDaysCount());
    }

    public static function computeSlaDeadlineFromMonths($months, $fromDate = null)
    {
        return Cedente::computeSlaDeadlineFromMonths($months, $fromDate);
    }

    /**
     * @param \DateTimeInterface|string|null $fromDate
     * @param int|null $months
     * @return string Y-m-d
     */
    public static function computeSlaApprovedDeadline($fromDate = null, $months = null)
    {
        return Cedente::computeSlaApprovedDeadline($fromDate, $months);
    }

    /**
     * @param string|null $status
     * @param \DateTimeInterface|string|null $fromDate
     * @return string|null
     */
    public static function computeSlaForStatus($status, $fromDate = null)
    {
        if (! self::shouldRecalculateSlaForStatus($status)) {
            return null;
        }

        if ($fromDate === null) {
            $fromDate = date('Y-m-d');
        }

        if ($status === self::STATUS_APROVADO) {
            return self::computeSlaApprovedDeadline($fromDate);
        }

        return self::computeSlaDeadline($fromDate);
    }

    public function enderecoResidencial()
    {
        return $this->belongsTo(Address::class, 'endereco_residencial_id');
    }

    public function enderecoComercial()
    {
        return $this->belongsTo(Address::class, 'endereco_comercial_id');
    }

    public function referencias()
    {
        return $this->hasMany(CotistaReferencia::class);
    }

    public function contas()
    {
        return $this->hasMany(CotistaConta::class);
    }

    public function itensPatrimoniais()
    {
        return $this->hasMany(CotistaItemPatrimonial::class);
    }

    public function participacoes()
    {
        return $this->hasMany(CotistaParticipacao::class);
    }

    public function operador()
    {
        return $this->hasOne(CotistaOperador::class);
    }

    public function cotistaFiles()
    {
        return $this->hasMany(CotistaFile::class);
    }

    public function suitability()
    {
        return $this->hasOne(CotistaSuitability::class);
    }

    public function audits()
    {
        return $this->hasMany(CotistaAudit::class);
    }

    public function inconsistencias()
    {
        return $this->hasMany(CotistaInconsistencia::class);
    }

    /**
     * @return string[]
     */
    public static function cadastroStatusValues()
    {
        return [
            self::STATUS_RASCUNHO,
            self::STATUS_PENDENTE,
            self::STATUS_EM_AVALIACAO,
            self::STATUS_INCONSISTENTE,
            self::STATUS_INCONSISTENCIA_JURIDICA,
            self::STATUS_APROVADO,
            self::STATUS_REJEITADO,
            self::STATUS_VENCIDO,
            self::STATUS_CANCELADO,
        ];
    }

    public static function isCadastroStatus($value)
    {
        return is_string($value) && in_array($value, self::cadastroStatusValues(), true);
    }

    /**
     * @return array<string, int>
     */
    public static function cadastroStatusCounts()
    {
        $counts = array_fill_keys(self::cadastroStatusValues(), 0);

        $rows = self::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $n = (int) $row->aggregate;
            $key = $row->status;
            if ($key === null || $key === '') {
                $counts[self::STATUS_PENDENTE] += $n;
            } elseif (isset($counts[$key])) {
                $counts[$key] += $n;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public static function cadastroStatusResumo()
    {
        return array_merge(self::cadastroStatusCounts(), [
            'total' => (int) self::query()->count(),
        ]);
    }

    /**
     * Relacoes usadas no detalhe da API.
     *
     * @return string[]
     */
    public static function apiRelations()
    {
        return [
            'enderecoResidencial',
            'enderecoComercial',
            'referencias',
            'contas',
            'itensPatrimoniais',
            'participacoes',
            'operador.address',
            'cotistaFiles',
            'suitability.respostas',
            'inconsistencias',
            'audits.user.employee',
        ];
    }
}
