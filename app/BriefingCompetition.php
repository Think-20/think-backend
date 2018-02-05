<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BriefingCompetition extends Model
{
    protected $table = 'briefing_competition';

    protected $fillable = [
        'description'
    ];
}
