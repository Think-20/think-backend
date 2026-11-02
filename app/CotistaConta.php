<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CotistaConta extends Model
{
    protected $table = 'cotista_conta';

    public const TIPO_POUPANCA = 'poupanca';
    public const TIPO_INVESTIMENTOS = 'investimentos';

    public static function tiposConta()
    {
        return [
            self::TIPO_POUPANCA => 'Poupanca',
            self::TIPO_INVESTIMENTOS => 'Investimentos',
        ];
    }

    protected $fillable = [
        'cotista_id',
        'banco',
        'agencia',
        'numero_conta',
        'tipo_conta',
    ];

    public function cotista()
    {
        return $this->belongsTo(Cotista::class);
    }
}
