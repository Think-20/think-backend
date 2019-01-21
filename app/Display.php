<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Display extends Model
{
    public $timestamps = false;

    protected $table = 'display';

    protected $fillable = [
        'url', 'description'
    ];
    
}
