<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BriefingMainExpectation extends Model
{
    protected $table = 'briefing_main_expectation';

    protected $fillable = [
        'description'
    ];
}
