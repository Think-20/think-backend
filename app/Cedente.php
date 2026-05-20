<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cedente extends Model
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_EM_AVALIACAO = 'em_avaliacao';
    public const STATUS_INCONSISTENTE = 'inconsistente';
    public const STATUS_APROVADO = 'aprovado';
    public const STATUS_VENCIDO = 'vencido';
    public const STATUS_CANCELADO = 'cancelado';
    protected $table = 'cedente';

    protected $fillable = [
        'nome',
        'documento',
        'email',
        'faturamento_anual',
        'minimo_assinantes',
        'address_id',
        'sistema_financeiro_nacional',
        'telefone',
        'status',
    ];

    protected $casts = [
        'sistema_financeiro_nacional' => 'boolean',
    ];

    public function address()
    {
        return $this->belongsTo(Address::class);
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

    /**
     * Valores persistidos em `cedente.status` (cadastro do cedente).
     *
     * @return string[]
     */
    public static function cadastroStatusValues()
    {
        return [
            self::STATUS_PENDENTE,
            self::STATUS_EM_AVALIACAO,
            self::STATUS_INCONSISTENTE,
            self::STATUS_APROVADO,
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
     * Contagens por status + total de cedentes (usado em `POST /cedentes/all` e `POST /cedentes/status-resumo`).
     *
     * @return array<string, int>
     */
    public static function cadastroStatusResumo()
    {
        return array_merge(self::cadastroStatusCounts(), [
            'total' => (int) self::query()->count(),
        ]);
    }
}
