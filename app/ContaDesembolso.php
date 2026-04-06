<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ContaDesembolso extends Model
{
    protected $table = 'conta_desembolso';

    public const TIPO_CONTA_CORRENTE = 'conta_corrente';
    public const TIPO_CONTA_POUPANCA = 'conta_poupanca';
    public const TIPO_CONTA_SALARIO = 'conta_salario';

    public static function tiposConta()
    {
        return [
            self::TIPO_CONTA_CORRENTE => 'Conta corrente',
            self::TIPO_CONTA_POUPANCA => 'Conta poupança',
            self::TIPO_CONTA_SALARIO => 'Conta salário',
        ];
    }

    protected $fillable = [
        'cedente_id',
        'tipo_conta',
        'codigo_banco',
        'agencia',
        'numero_conta',
        'digito_conta',
        'descricao',
    ];

    public function cedente()
    {
        return $this->belongsTo(Cedente::class);
    }
}
