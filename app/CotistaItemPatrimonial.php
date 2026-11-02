<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CotistaItemPatrimonial extends Model
{
    protected $table = 'cotista_item_patrimonial';

    protected $fillable = [
        'cotista_id',
        'categoria',
        'descricao',
        'valor_atual',
    ];

    public function cotista()
    {
        return $this->belongsTo(Cotista::class);
    }
}
