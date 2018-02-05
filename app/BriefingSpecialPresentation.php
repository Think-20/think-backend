<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BriefingSpecialPresentation extends Model
{
    protected $table = 'briefing_special_presentation';

    protected $fillable = [
        'description'
    ];
}
