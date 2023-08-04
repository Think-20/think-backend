<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JobHowCome extends Model
{
    protected $table = 'job_how_come';

    protected $fillable = [
        'description'
    ];
}
