<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DateTime;
use DateInterval;

class Task extends Model
{

    public $timestamps = false;
    protected $table = 'task';
    protected $fillable = [
        'job_id', 'responsible_id', 'available_date', 'job_activity_id', 'duration'
    ];

    public static function getNextAvailableDate($availableDate, $estimatedTime, $jobActivity)
    {
        $taskBuild = TaskFactory::build($jobActivity);
        $jobActivity = JobActivity::where('description', '=', $jobActivity)->first();
        $responsibles = $taskBuild->getResponsibleList();

        $arr = ActivityHelper::calculateNextDate($availableDate, $jobActivity, $responsibles, $estimatedTime);

        return [
            'available_date' => ($arr['date'])->format('Y-m-d'),
            'responsible' => $arr['responsible'],
            'responsibles' => $responsibles
        ];
    }

    public static function editAvailableDate(array $data)
    {
        $task1 = isset($data['task1']['id']) ? Task::find($data['task1']['id']) : (object) $data['task1'];
        $task2 = isset($data['task2']['id']) ? Task::find($data['task2']['id']) : (object) $data['task2'];

        if($task1->duration == $task2->duration) {
            $tempR = $task1->responsible_id;
            $tempA = $task1->available_date;
            $tempD = $task1->duration;
            $tempItems = $task1->items;
            
            $task1->responsible_id = $task2->responsible_id;
            $task1->available_date = $task2->available_date;
            $task1->duration = $task2->duration;
            $task1->save();
            
            foreach($task2->items as $item) {
                $item->task_id = $task1->id;
                $item->save();
            }
            
            $task2->responsible_id = $tempR;
            $task2->available_date = $tempA;
            $task2->duration = $tempD;
            $task2->save();
            
            foreach($tempItems as $item) {
                $item->task_id = $task2->id;
                $item->save();
            }
        } else {
            throw new \Exception('A duração das tarefas estão diferentes.');
        }
        
        return true;
    }

    public static function myEditAvailableDate(array $data)
    {
        $id = $data['id'];
        $task = Task::find($id);
        $available_date = isset($data['available_date']) ? $data['available_date'] : null;

        if ($task->job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para editar esse job.');
        }

        $task->update(['available_date' => $available_date]);
        return $task;
    }

    public static function insert(array $data)
    {
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        $job_id = isset($data['job']['id']) ? $data['job']['id'] : null;
        $job_activity_id = isset($data['job_activity']['id']) ? $data['job_activity']['id'] : null;
        $task = new Task(array_merge($data, [
            'responsible_id' => $responsible_id,
            'job_id' => $job_id,
            'job_activity_id' => $job_activity_id
        ]));

        $task->save();
        $task->saveItems();

        return $task;
    }

    public function saveItems()
    {
        $date = new DateTime($this->available_date);
        $taskIsThisDate = TaskItem::select('*', 'task_item.duration as task_duration')
            ->leftJoin('task', 'task.id', '=', 'task_item.task_id')
            ->where('responsible_id', '=', $this->responsible->id)
            ->where('job_activity_id', '=', $this->job_activity->id)
            ->where('available_date', '=', $this->available_date)
            ->get();

        $duration = $this->duration;
        if ($taskIsThisDate->count() > 0) {
            $duration .= -$taskIsThisDate->task_duration;
            TaskItem::insert([
                'duration' => $this->duration - $duration,
                'date' => $date->format('Y-m-d'),
                'task_id' => $this->id
            ]);
            $date = DateHelper::sumUtil($date, 1);
        }

        $tempDuration = $duration;
        for ($i = 0; $i < $duration; $i++) {
            $fator = $tempDuration >= 1
                ? 1
                : $tempDuration;

            $tempDuration .= -$fator;
            TaskItem::insert([
                'duration' => $fator,
                'date' => $date->format('Y-m-d'),
                'task_id' => $this->id
            ]);
            $date = DateHelper::sumUtil($date, 1);
        }
    }

    public static function edit(array $data)
    {
        $id = $data['id'];
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        $task = Task::find($id);

        $task->update(
            array_merge($data, [
                'responsible_id' => $responsible_id
            ])
        );
    }

    public static function filter($params)
    {
        $iniDate = isset($params['iniDate']) ? $params['iniDate'] : null;
        $finDate = isset($params['finDate']) ? $params['finDate'] : null;
        $paginate = isset($params['paginate']) ? $params['paginate'] : true;

        $tasks = Task::select();

        if (!is_null($iniDate) && !is_null($finDate)) {
            $sql = '(task.available_date >= "' . $iniDate . '"';
            $sql .= ' AND task.available_date <= "' . $finDate . '")';
            $sql .= 'OR (task.available_date >= "' . $iniDate . '"';
            $sql .= ' AND task.available_date <= "' . $finDate . '")';
            $tasks->whereRaw($sql);
        }

        $tasks->orderBy('task.available_date', 'ASC');

        if ($paginate) {
            $paginate = $tasks->paginate(50);

            foreach($paginate as $task) {
                $task->job = Job::get($task->job_id);
                $task->items;
                $task->responsible;
                $task->job_activity;
            }

            $result = $paginate->items();
            $page = $paginate->currentPage();
            $total = $paginate->total();
        } else {
            $result = $tasks->get();

            foreach($result as $task) {
                $task->job = Job::get($task->job_id);
                $task->items;
                $task->responsible;
                $task->job_activity;
            }

            $total = $tasks->count();
            $page = 0;
        }

        return [
            'data' => $result,
            'total' => $total,
            'page' => $page
        ];
    }

    public function remove()
    {
        $this->items->delete();
        $this->delete();
    }

    public function get()
    {
        $this->presentations;
        $this->responsible;
    }

    public function items()
    {
        return $this->hasMany('App\TaskItem', 'task_id');
    }

    public function job()
    {
        return $this->belongsTo('App\Job', 'job_id');
    }

    public function job_activity()
    {
        return $this->belongsTo('App\JobActivity', 'job_activity_id');
    }

    public function responsible()
    {
        return $this->belongsTo('App\Employee', 'responsible_id');
    }

    public function setDurationAttribute($value)
    {
        $this->attributes['duration'] = (float)str_replace(',', '.', $value);
    }
}
