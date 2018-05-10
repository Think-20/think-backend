<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BriefingFile extends Model
{
    public $timestamps = false;
    
    protected $table = 'briefing_file';

    protected $fillable = [
        'filename', 'briefing_id'
    ];
}
