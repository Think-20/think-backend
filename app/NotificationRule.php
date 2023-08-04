<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NotificationRule extends Model
{
    protected $table = 'notification_rule';

    protected $fillable = [
        'type_id', 'user_id'
    ];

    public function type() {
        return $this->belongsTo('App\NotificationType', 'type_id');
    }

    public function user() {
        return $this->belongsTo('App\User', 'user_id');
    }
}
