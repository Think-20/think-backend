<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notification';

    protected $fillable = [
        'date', 'message', 'type_id', 'notifiable_id', 'notifiable_type', 'json_data'
    ];

    public static function createAndNotify(NotifierInterface $notifier, array $data, array $notificationSpecial, string $type, $info = null) {
        Notification::emit(NotificationType::findByDescription($type), $notifier, $data, $notificationSpecial, $info);
    }

    protected static function emit(NotificationType $type, NotifierInterface $notifier, array $data, array $notificationSpecial, $info) {
        if($type->active == 0) return;

        $data = array_merge($data, [
            'date' => (new Date())->format('Y-m-d H:i:s'),
            'type_id' => $type->id,
            'info' => $info
        ]);
        $notification = new Notification($data);
        $notification->save();
        $notification->notifier()->save($notifier);
        $notification->notify($type, $notifier, $notificationSpecial);
    }

    protected function notify(NotificationType $type, NotifierInterface $notifier, array $notificationSpecial) {
        $ableUsersForType = NotificationRule::where('type_id', '=', $type->id)
        ->where('user_id','<>',$notifier->getOriginalId())
        ->get();
        
        foreach($ableUsersForType as $ableUserForType) {
            $data = [
                'notification_id' => $this->id,
                'user_id' => $ableUserForType->user_id 
            ];

            foreach($notificationSpecial as $special) {
                if($special->user_id != $ableUserForType->user_id) return;

                $data = array_merge($data, [
                    'special' => 1,
                    'special_message' => $special->message
                ]);
            }

            UserNotification::create($data);
        }
    }

    public function type() {
        return $this->belongsTo('App\NotificationType', 'type_id');
    }

    public function user() {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function notifier() {
        return $this->morphTo();
    }
}