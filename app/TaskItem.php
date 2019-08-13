<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskItem extends Model
{
    public $timestamps = false;

    protected $table = 'task_item';

    protected $fillable = [
        'date', 'task_id', 'duration', 'budget_value'
    ];

    public static function insert(Task $task, array $data) {
        return TaskItem::create(array_merge($data, [ 'task_id' => $task->id ]));
    }

    public static function insertAll(Task $task, array $items) {
        foreach($items as $item) {
            $item = (object) $item;
            TaskItem::insert($task, [
                'date' => $item->date,
                'duration' => $item->duration,
                'budget_value' => $item->budget_value,
            ]);
        }
    }

    public function setBudget_valueAttribute($value) {
        $this->attributes['budget_value'] = (float) str_replace(',', '.', str_replace('.', '', $value));
    }

    public function setDateAttribute($value) {
        $this->attributes['date'] = substr($value, 0, 10);
    }

    public function task() {
        return $this->belongsTo('App\Task', 'task_id');
    }
}
