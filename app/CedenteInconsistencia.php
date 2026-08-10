<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CedenteInconsistencia extends Model
{
    /** Observação do avalista ao solicitar correções (`solicitar_correcoes`). */
    const CAMPO_APROVADOR = 'aprovador';

    protected $table = 'cedente_inconsistencia';

    protected $fillable = [
        'cedente_id',
        'campo_inconsistente',
        'valor_serpro',
    ];

    public function cedente()
    {
        return $this->belongsTo(Cedente::class);
    }
}
