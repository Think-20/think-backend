<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JobFile extends Model
{
    public $timestamps = false;
    
    protected $table = 'job_file';

    protected $fillable = [
        'filename', 'job_id'
    ];
}
