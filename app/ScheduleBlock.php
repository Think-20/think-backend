<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DateTime;

class ScheduleBlock extends Model
{
    public $timestamps = false;

    protected $table = 'schedule_block';

    protected $fillable = [
        'date',
    ];

    public static function saveOrRemove(array $data) {
        $users = $data['users'];
        $scheduleBlock = new ScheduleBlock($data);
        $found = ScheduleBlock::where('date', '=', $scheduleBlock->date)->first();

        if($found == null) {
            ScheduleBlock::insert($data);
        } else if(count($users) == 0) {
            ScheduleBlock::remove($found->id);
        } else {
            ScheduleBlock::remove($found->id);
            ScheduleBlock::insert($data);
        }
    }

    public static function insert(array $data) {
        $scheduleBlock = new ScheduleBlock($data);
        $scheduleBlock->save();
        $users = $data['users'];

        foreach($users as $user) {
            $scheduleBlock->blocks()->create([
                'user_id' => $user['id'],
                'schedule_id' => $scheduleBlock->id
            ]);
        }

        return $scheduleBlock;
    }

    public static function sumUtilNonBlocked(DateTime $date, User $user, $interval) {
        $date = DateHelper::sumUtil($date, $interval);

        while(ScheduleBlock::checkIfBlocked($date->format('Y-m-d'), $user->id)) {
            $date = DateHelper::sumUtil($date, $interval);
        }

        return $date;
    }

    public static function checkIfBlocked(string $availableDate, $userId) {
        $sb = $scheduleBlock = ScheduleBlock::with(['blocks' => function($query) use ($userId) {
            $query->where('user_id', '=', $userId);
        }])->where('date', '=', $availableDate)->first();

        return $sb != null && count($sb->blocks) > 0;
    }
    
    public static function valid() {
        $date = new DateTime();
        $date1 = DateHelper::sub(new DateTime(), 31);
        $date2 = DateHelper::sum(new DateTime(), 90);

        return ScheduleBlock::with('blocks')
        ->where('date', '>=', $date1->format('Y-m') . '-01')
        ->where('date', '<=', $date2->format('Y-m') . '-31')
        ->get();
    }

    public static function myValid() {
        $date1 = DateHelper::sub(new DateTime(), 31);
        $date2 = DateHelper::sum(new DateTime(), 31);
        $userId = User::logged()->id;

        return ScheduleBlock::with('blocks')
        ->where('date', '>=', $date1->format('Y-m') . '-01')
        ->where('date', '<=', $date2->format('Y-m') . '-31')
        ->whereHas('blocks', function($query) use ($userId) {
            $query->where('user_id', '=', $userId);
        })
        ->get();
    }
    
    public static function remove(int $id) {
        $scheduleBlock = ScheduleBlock::find($id);
        $scheduleBlock->blocks()->delete();
        $scheduleBlock->delete();
    }

    public function blocks() {
        return $this->hasMany('App\ScheduleBlockUser', 'schedule_id');
    }
    
    public function setDateAttribute($value) {
        $this->attributes['date'] = substr($value, 0, 10);
    }
}
