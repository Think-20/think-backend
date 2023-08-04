<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DisplayUser extends Model
{
    public $timestamps = false;

    protected $table = 'display_user';

    protected $fillable = [
        'user_id', 'display_user'
    ];
    
}
