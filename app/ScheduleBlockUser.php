<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ScheduleBlockUser extends Model
{
    public $timestamps = false;

    protected $table = 'schedule_block_user';

    protected $fillable = [
        'user_id', 'schedule_id'
    ];
}
