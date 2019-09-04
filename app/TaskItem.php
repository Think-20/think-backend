<?php

namespace App;

use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Model;

class TaskItem extends Model
{
    public $timestamps = false;

    protected $table = 'task_item';

    protected $fillable = [
        'date', 'task_id', 'duration', 'budget_value'
    ];

    public static function swapItems($itemTask1Id, $itemTask2Id = null, $targetDate = null)
    {
        $collectIds = collect([]);
        $itemTask1 = TaskItem::find($itemTask1Id);
        $collectIds->push($itemTask1->id);

        if ($itemTask2Id != null) {
            $itemTask2 = TaskItem::find($itemTask2Id);
            $collectIds->push($itemTask2->id);

            if ($itemTask1->task->responsible_id != $itemTask2->task->responsible_id)
                throw new Exception('Os itens devem pertencer ao mesmo responsável!');

            if ($itemTask1->task_id == $itemTask2->task_id)
                throw new Exception('Os itens não podem pertencer a mesma tarefa!');

            if ($itemTask1->duration != $itemTask2->duration)
                throw new Exception('As durações dos itens devem ser iguais!');

            $nextDates2 = TaskHelper::getDates(
                new DateTime($itemTask1->date),
                new DateTime($itemTask1->date),
                $itemTask2->task->job_activity,
                [$itemTask2->task->responsible_id],
                $collectIds
            );

            $datesAvailable2 = $nextDates2->filter(function ($item) {
                return $item->status == 'true';
            });

            $item = $nextDates2->first();

            if ($datesAvailable2->count() == 0) {
                throw new Exception($item->message);
            }
            
            $targetDate = $itemTask2->date;
        }

        $nextDates = TaskHelper::getDates(
            new DateTime($targetDate),
            new DateTime($targetDate),
            $itemTask1->task->job_activity,
            [$itemTask1->task->responsible_id],
            $collectIds
        );

        $datesAvailable1 = $nextDates->filter(function ($item) {
            return $item->status == 'true';
        });

        $item = $nextDates->first();

        if ($datesAvailable1->count() == 0) {
            throw new Exception($item->message);
        }

        $itemTask1->date = $targetDate;
        $itemTask1->save();

        if ($itemTask2Id != null) {
            $itemTask2->date = $itemTask1->date;
            $itemTask2->save();
        }
    }

    public static function insert(Task $task, array $data)
    {
        return TaskItem::create(array_merge($data, ['task_id' => $task->id]));
    }

    public static function insertAll(Task $task, array $items)
    {
        foreach ($items as $item) {
            $item = (object) $item;
            TaskItem::insert($task, [
                'date' => $item->date,
                'duration' => $item->duration,
                'budget_value' => $item->budget_value,
            ]);
        }
    }

    public static function durationSub($num)
    {
        return 1 - $num;
    }

    public function setBudget_valueAttribute($value)
    {
        $this->attributes['budget_value'] = (float) str_replace(',', '.', str_replace('.', '', $value));
    }

    public function setDateAttribute($value)
    {
        $this->attributes['date'] = substr($value, 0, 10);
    }

    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id');
    }
}
