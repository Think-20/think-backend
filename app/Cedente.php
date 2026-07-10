<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cedente extends Model
{
    public const STATUS_RASCUNHO = 'rascunho';
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_EM_AVALIACAO = 'em_avaliacao';
    public const STATUS_INCONSISTENTE = 'inconsistente';
    public const STATUS_APROVADO = 'aprovado';
    public const STATUS_SOLICITAR_CORRECOES = 'solicitar_correcoes';
    public const STATUS_REJEITADO = 'rejeitado';
    public const STATUS_VENCIDO = 'vencido';
    public const STATUS_CANCELADO = 'cancelado';

    /** Prazo padrão do cadastro (dias úteis após a data de referência). */
    public const SLA_DIAS_UTEIS_DEFAULT = 5;

    /** Prazo do cadastro aprovado (meses corridos após a data de referência). */
    public const SLA_MESES_APROVADO_DEFAULT = 3;

    protected $table = 'cedente';

    protected $fillable = [
        'fund_id',
        'nome',
        'documento',
        'email',
        'faturamento_anual',
        'minimo_assinantes',
        'address_id',
        'sistema_financeiro_nacional',
        'telefone',
        'observacao',
        'limite_aprovado',
        'prazo_atualizacao_cadastral',
        'status',
        'sla',
    ];

    public const AVALIACAO_APROVADO = 'aprovado';

    public const AVALIACAO_SOLICITAR_CORRECOES = 'solicitar_correcoes';

    public const AVALIACAO_REJEITADO = 'rejeitado';

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

    protected $casts = [
        'sistema_financeiro_nacional' => 'boolean',
    ];

    protected $dates = [
        'sla',
    ];

    /**
     * Quantidade de dias úteis para o SLA (env `CEDENTE_SLA_DIAS_UTEIS`, padrão 5).
     */
    public static function slaBusinessDaysCount()
    {
        $n = (int) env('CEDENTE_SLA_DIAS_UTEIS', self::SLA_DIAS_UTEIS_DEFAULT);

        return $n > 0 ? $n : self::SLA_DIAS_UTEIS_DEFAULT;
    }

    /**
     * Status em que o SLA não é recalculado (mantém o valor atual).
     */
    public static function slaSkippedStatuses()
    {
        return [
            self::STATUS_RASCUNHO,
            self::STATUS_INCONSISTENTE,
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
     * Data limite do SLA a partir de $fromDate (Y-m-d ou DateTime).
     *
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
        if ($fromDate === null) {
            $fromDate = date('Y-m-d');
        }

        $months = (int) $months;
        if ($months < 1) {
            $months = self::SLA_MESES_APROVADO_DEFAULT;
        }

        $dt = $fromDate instanceof \DateTimeInterface
            ? \DateTime::createFromFormat('Y-m-d', $fromDate->format('Y-m-d'))
            : new \DateTime(substr((string) $fromDate, 0, 10));
        $dt->modify('+' . $months . ' months');

        return $dt->format('Y-m-d');
    }

    /**
     * SLA de cadastro aprovado: +3 meses corridos a partir de $fromDate.
     *
     * @param \DateTimeInterface|string|null $fromDate
     * @return string Y-m-d
     */
    public static function computeSlaApprovedDeadline($fromDate = null, $months = null)
    {
        if ($months !== null && (int) $months > 0) {
            return self::computeSlaDeadlineFromMonths((int) $months, $fromDate);
        }

        if ($fromDate === null) {
            $fromDate = date('Y-m-d');
        }

        $dt = $fromDate instanceof \DateTimeInterface
            ? \DateTime::createFromFormat('Y-m-d', $fromDate->format('Y-m-d'))
            : new \DateTime(substr((string) $fromDate, 0, 10));
        $monthsDefault = (int) env('CEDENTE_SLA_MESES_APROVADO', self::SLA_MESES_APROVADO_DEFAULT);
        if ($monthsDefault < 1) {
            $monthsDefault = self::SLA_MESES_APROVADO_DEFAULT;
        }
        $dt->modify('+' . $monthsDefault . ' months');

        return $dt->format('Y-m-d');
    }

    /**
     * Calcula a data limite do SLA conforme o status (na data de referência).
     *
     * @param string|null $status
     * @param \DateTimeInterface|string|null $fromDate
     * @return string|null Y-m-d ou null se o status não recalcula SLA
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

    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $fundId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForFund($query, $fundId)
    {
        return $query->where('fund_id', (int) $fundId);
    }

    /**
     * Todas as pessoas ligadas ao cedente (parte relacionada e/ou avalista).
     */
    public function pessoasVinculadas()
    {
        return $this->hasMany(CedentePessoaVinculada::class);
    }

    public function partesRelacionadas()
    {
        return $this->hasMany(CedentePessoaVinculada::class)->where('e_parte_relacionada', true);
    }

    public function avalistas()
    {
        return $this->hasMany(CedentePessoaVinculada::class)->where('e_avalista', true);
    }

    public function contasDesembolso()
    {
        return $this->hasMany(ContaDesembolso::class);
    }

    public function cedenteFiles()
    {
        return $this->hasMany(CedenteFile::class);
    }

    public function audits()
    {
        return $this->hasMany(CedenteAudit::class);
    }

    public function inconsistencias()
    {
        return $this->hasMany(CedenteInconsistencia::class);
    }

    /**
     * Valores persistidos em `cedente.status` (cadastro do cedente).
     *
     * @return string[]
     */
    public static function cadastroStatusValues()
    {
        return [
            self::STATUS_RASCUNHO,
            self::STATUS_PENDENTE,
            self::STATUS_EM_AVALIACAO,
            self::STATUS_INCONSISTENTE,
            self::STATUS_APROVADO,
            self::STATUS_SOLICITAR_CORRECOES,
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
     * Contagem por status atual (uma linha por cedente).
     *
     * @return array<string, int>
     */
    public static function cadastroStatusCounts($fundId = null)
    {
        $counts = array_fill_keys(self::cadastroStatusValues(), 0);

        $query = self::query();
        if ($fundId !== null) {
            $query->forFund($fundId);
        }

        $rows = $query
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
     * Contagens por status + total de cedentes (usado em `POST /cedentes/all` e `POST /cedentes/status-resumo`).
     *
     * @return array<string, int>
     */
    public static function cadastroStatusResumo($fundId = null)
    {
        $query = self::query();
        if ($fundId !== null) {
            $query->forFund($fundId);
        }

        return array_merge(self::cadastroStatusCounts($fundId), [
            'total' => (int) $query->count(),
            'fund_id' => $fundId !== null ? (int) $fundId : null,
        ]);
    }
}
