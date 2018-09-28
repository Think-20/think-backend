<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DateTime;
use DB;

class UserNotification extends Model
{
    public $timestamps = false;

    protected $table = 'user_notification';

    protected $fillable = [
        'received_date', 'received', 'notification_id', 'user_id', 'read', 'read_date',
        'special', 'special_message'
    ];
    
    
    public static function read(array $data) {
        DB::beginTransaction();
                
        try {
            foreach($data['ids'] as $id) {
                $userNotification = UserNotification::find($id);
                $userNotification->update(['read' => 1, 'read_date' => (new DateTime())->format('Y-m-d H:i:s')]);
            }
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }
    
    public static function list() {
        $usersNotification = UserNotification::select('user_notification.*')
        ->with('notification', 'notification.type', 'notification.notifier')
        ->leftJoin('notification', 'notification.id', '=', 'user_notification.notification_id')
        ->where('user_notification.user_id', '=', User::logged()->id)
        ->orderBy('notification.date', 'desc')
        ->paginate(20);
        
        return [
            'pagination' => [
                'data' => $usersNotification,
            ],
            'updatedInfo' => UserNotification::updatedInfo()
        ];
    }
    
    public static function recents() {
        $usersNotification = UserNotification::select('user_notification.*')
        ->with(['notification', 'notification.type', 'notification.notifier'])
        ->leftJoin('notification', 'notification.id', '=', 'user_notification.notification_id')
        ->where('user_notification.user_id', '=', User::logged()->id)
        ->where('received', '=', '1')
        ->orderBy('notification.date', 'desc')
        ->limit(100)
        ->get();
     
        return [
            'pagination' => [
                'data' => $usersNotification,
            ],
            'updatedInfo' => UserNotification::updatedInfo()
        ];
    }
    
    public static function listen() {
        $usersNotification = UserNotification::select('user_notification.*')
        ->with(['notification', 'notification.type', 'notification.notifier'])
        ->leftJoin('notification', 'notification.id', '=', 'user_notification.notification_id')
        ->where('user_notification.user_id', '=', User::logged()->id)
        ->where('received', '=', '0')
        ->orderBy('notification.date', 'desc')
        ->get();

        UserNotification::whereIn('id', $usersNotification->map(function($u) { return $u->id; }))
        ->update(['received' => 1, 'received_date' => (new DateTime())->format('Y-m-d H:i:s')]);     

        return $usersNotification;
    }

    public static function updatedInfo() {
        $lastData = UserNotification::where('user_id', '=', User::logged()->id)
        ->orderBy('id', 'desc')->limit(1)->first();

        if($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->notification->date))->format('d/m/Y'),
            'by' => $lastData->notification->notifier->getName()
        ];
    }

    public function notification() {
        return $this->belongsTo('App\Notification', 'notification_id');
    }

    public function user() {
        return $this->belongsTo('App\User', 'user_id');
    }
}
