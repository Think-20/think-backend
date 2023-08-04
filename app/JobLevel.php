<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JobLevel extends Model
{
    protected $table = 'job_level';

    protected $fillable = [
        'description'
    ];
}
