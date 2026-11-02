<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CotistaSuitabilityResposta extends Model
{
    protected $table = 'cotista_suitability_resposta';

    protected $fillable = [
        'cotista_suitability_id',
        'pergunta',
        'opcao',
    ];

    public function suitability()
    {
        return $this->belongsTo(CotistaSuitability::class, 'cotista_suitability_id');
    }
}
