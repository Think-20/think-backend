<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NotificationType extends Model
{
    protected $table = 'notification_type';

    protected $fillable = [
        'description', 'genre_id', 'active'
    ];

    public function genre() {
        return $this->belongsTo('App\NotificationGenre', 'genre_id');
    }

    public static function findByDescription(string $description): NotificationType {
        return NotificationType::where('description', '=', $description)->first();
    }
}
