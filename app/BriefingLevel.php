<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BriefingLevel extends Model
{
    protected $table = 'briefing_level';

    protected $fillable = [
        'description'
    ];
}
