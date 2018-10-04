<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

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

    public static function remove(int $id) {
        $scheduleBlock = ScheduleBlock::find($id);
        $scheduleBlock->delete();
    }
}
