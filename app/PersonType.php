<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PersonType extends Model
{
    protected $table = 'person_type';

    protected $fillable = [
        'description'
    ];
}
