<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BriefingStatus extends Model
{
    protected $table = 'briefing_status';

    protected $fillable = [
        'description'
    ];
}
