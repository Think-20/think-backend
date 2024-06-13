<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InactiveTime extends Model
{
    protected $table = 'inactive_time';


    protected $fillable = [
        'type', 'notification_time', 'inactive_time'
    ];
}
