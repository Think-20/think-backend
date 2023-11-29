<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    protected $table = 'goal';


    protected $fillable = [
        'month', 'year', 'value', 'expected_value'
    ];
}
