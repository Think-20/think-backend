<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserFunctionality extends Model
{
    public $timestamps = false;

    protected $table = 'user_functionality';

    protected $fillable = [
        'id',
        'user_id',
        'functionality_id'
    ];
}
