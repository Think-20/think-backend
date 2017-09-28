<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClientStatus extends Model
{
    public $timestamps = false;

    protected $table = 'client_status';

    protected $fillable = [
        'description'
    ];
}
