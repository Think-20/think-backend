<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NotificationGenre extends Model
{
    protected $table = 'notification_genre';

    protected $fillable = [
        'description'
    ];
}
