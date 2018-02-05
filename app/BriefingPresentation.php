<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BriefingPresentation extends Model
{
    protected $table = 'briefing_presentation';

    protected $fillable = [
        'description'
    ];
}
