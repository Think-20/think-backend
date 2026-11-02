<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CotistaParticipacao extends Model
{
    protected $table = 'cotista_participacao';

    protected $fillable = [
        'cotista_id',
        'razao_social',
        'cnpj',
        'nacionalidade',
        'percentual_capital',
        'instituicao_financeira',
        'giin',
    ];

    protected $casts = [
        'instituicao_financeira' => 'boolean',
    ];

    public function cotista()
    {
        return $this->belongsTo(Cotista::class);
    }
}
