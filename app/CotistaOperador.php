<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CotistaOperador extends Model
{
    protected $table = 'cotista_operador';

    protected $fillable = [
        'cotista_id',
        'opera_conta_propria',
        'autoriza_ordens_procurador',
        'nome_completo',
        'cpf',
        'data_nascimento',
        'address_id',
        'descricao_poderes',
        'e_ppe',
        'ppe_cargo',
        'relacionamento_ppe',
        'ppe_relacionada_cargo',
        'ppe_relacionada_nome',
        'ppe_relacionada_cpf',
        'ppe_relacionada_nacionalidade',
    ];

    protected $casts = [
        'opera_conta_propria' => 'boolean',
        'autoriza_ordens_procurador' => 'boolean',
        'e_ppe' => 'boolean',
        'relacionamento_ppe' => 'boolean',
    ];

    protected $dates = [
        'data_nascimento',
    ];

    public function cotista()
    {
        return $this->belongsTo(Cotista::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}
