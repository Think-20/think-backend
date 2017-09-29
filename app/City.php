<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    public $timestamps = false;

    protected $table = 'city';

    protected $fillable = [
        'name', 'state_id'
    ];

    public function state() {
        return $this->belongsTo('App\State', 'state_id');
    }
}
