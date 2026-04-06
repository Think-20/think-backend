<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CedentePessoaVinculada extends Model
{
    protected $table = 'cedente_pessoa_vinculada';

    public const TIPO_SOCIO = 1;
    public const TIPO_ADMINISTRADOR = 2;
    public const TIPO_PROCURADOR = 3;
    public const TIPO_REPRESENTANTE_LEGAL = 4;

    protected $fillable = [
        'cedente_id',
        'e_parte_relacionada',
        'e_avalista',
        'nome',
        'tipo_parte_relacionada',
        'nacionalidade',
        'email',
        'cpf',
        'telefone',
        'beneficiario_final',
        'assinante_operacao',
        'assinante_obrigatorio',
        'estado_civil',
        'regime_casamento',
        'profissao',
        'address_id',
    ];

    protected $casts = [
        'e_parte_relacionada' => 'boolean',
        'e_avalista' => 'boolean',
        'beneficiario_final' => 'boolean',
        'assinante_operacao' => 'boolean',
        'assinante_obrigatorio' => 'boolean',
    ];

    public function scopeParteRelacionada(Builder $query)
    {
        return $query->where('e_parte_relacionada', true);
    }

    public function scopeAvalista(Builder $query)
    {
        return $query->where('e_avalista', true);
    }

    public function cedente()
    {
        return $this->belongsTo(Cedente::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}
