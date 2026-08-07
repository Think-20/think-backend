<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CedenteRestricao extends Model
{
    protected $table = 'cedente_restricao';

    protected $fillable = [
        'cedente_id',
        'campo_restrito',
        'socio_indice',
        'socio_nome',
        'qualificacao_representante_legal',
        'nome_representante_legal',
        'codigo',
        'descricao',
        'valor_vadu',
        'fonte',
    ];

    public function cedente()
    {
        return $this->belongsTo(Cedente::class);
    }
}
