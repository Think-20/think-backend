<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JobCompetition extends Model
{
    protected $table = 'job_competition';

    protected $fillable = [
        'description'
    ];
}
