<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StandConfiguration extends Model
{
    protected $table = 'stand_configuration';

    protected $fillable = [
        'description'
    ];
}
