<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CotistaReferencia extends Model
{
    protected $table = 'cotista_referencia';

    protected $fillable = [
        'cotista_id',
        'nome_completo',
        'nacionalidade',
        'telefone',
    ];

    public function cotista()
    {
        return $this->belongsTo(Cotista::class);
    }
}
