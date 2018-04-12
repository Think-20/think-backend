<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TimecardPlace extends Model
{
    protected $table = 'timecard_place';

    protected $fillable = [
        'description'
    ];
}
