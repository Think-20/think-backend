<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CotistaInconsistencia extends Model
{
    /** Observacao do aprovador ao solicitar correcoes. */
    const CAMPO_APROVADOR = 'aprovador';

    protected $table = 'cotista_inconsistencia';

    protected $fillable = [
        'cotista_id',
        'campo_inconsistente',
        'valor_serpro',
    ];

    public function cotista()
    {
        return $this->belongsTo(Cotista::class);
    }
}
