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

    public static function insert(array $data) {
        return TaskItem::create($data);
    }

    public function setBudget_valueAttribute($value) {
        $this->attributes['budget_value'] = (float) str_replace(',', '.', str_replace('.', '', $value));
    }

    public function task() {
        return $this->belongsTo('App\Task', 'task_id');
    }
}
