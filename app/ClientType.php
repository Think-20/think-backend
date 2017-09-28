<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClientType extends Model
{
    public $timestamps = false;

    protected $table = 'client_type';

    protected $fillable = [
        'description'
    ];
}
