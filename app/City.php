<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    public $timestamps = false;

    protected $table = 'city';

    protected $fillable = [
        'name', 'stateId'
    ];

    public function state() {
        return $this->belongsTo('App\State', 'stateId');
    }
}
