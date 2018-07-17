<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskItem extends Model
{
    public $timestamps = false;

    protected $table = 'task_item';

    protected $fillable = [
        'date', 'task_id', 'duration'
    ];

    public static function insert(array $data) {
        return TaskItem::create($data);
    }
}
