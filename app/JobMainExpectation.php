<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JobMainExpectation extends Model
{
    protected $table = 'job_main_expectation';

    protected $fillable = [
        'description'
    ];
}
