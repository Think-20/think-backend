<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cedente extends Model
{
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
}
