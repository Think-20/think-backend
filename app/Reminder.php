<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $table = 'reminders';

    protected $fillable = [
        'message',
        'read',
        'metadata',
        'employee_id',
        'category'
    ];
}
