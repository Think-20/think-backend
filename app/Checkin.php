<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Checkin extends Model
{
    protected $table = 'checkin';

    protected $guarded = ['id'];

    //protected $fillable = array("*");
}
