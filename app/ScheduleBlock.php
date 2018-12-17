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

    public static function insert(array $data) {
        $scheduleBlock = new ScheduleBlock($data);
        $scheduleBlock->save();

        return $scheduleBlock;
    }

    public static function checkIfBlocked(string $availableDate, $userId) {
        return ScheduleBlock::where('date', '=', $availableDate)
        ->leftJoin('schedule_block_user', 'schedule_block_user.schedule_id', '=', 'schedule_block.id')
        ->where('schedule_block_user.user_id', '=', $userId)
        ->first() != null;
    }

    public static function valid() {
        $date = new DateTime();
        $date1 = DateHelper::sub(new DateTime(), 31);
        $date2 = DateHelper::sum(new DateTime(), 31);

        return ScheduleBlock::where('date', '>=', $date1->format('Y-m') . '-01')
        ->where('date', '<=', $date2->format('Y-m') . '-31')
        ->leftJoin('schedule_block_user', 'schedule_block_user.schedule_id', '=', 'schedule_block.id')
        ->where('schedule_block_user.user_id', '=', User::logged()->id)
        ->get();
    }
    
    public static function remove(int $id) {
        $scheduleBlock = ScheduleBlock::find($id);
        $scheduleBlock->delete();
    }

    public function blocks() {
        return $this->hasMany('App\ScheduleBlockUser', 'schedule_id');
    }
}
