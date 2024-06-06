<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StandGenre extends Model
{
    protected $table = 'stand_genre';

    protected $fillable = [
        'description'
    ];
}
