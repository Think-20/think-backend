<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Functionality extends Model
{
    public $timestamps = false;

    protected $table = 'functionality';

    protected $fillable = [
        'url', 'description'
    ];
    
}
