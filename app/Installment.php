<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    protected $table = 'installment';

    protected $fillable = [
        'transaction_id', 'value', 'date', 'order'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
