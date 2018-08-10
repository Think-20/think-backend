<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DateTime;
use DateInterval;

class Task extends Model
{
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
            'available_date' => $arr['date'],
            'available_responsibles' => $arr['available_responsibles'],
            'responsibles' => $responsibles
        ];
    }

    public static function getNextAvailableDates(array $data)
    {
        $jobActivityId = isset($data['job_activity']['id']) ? $data['job_activity']['id'] : null;
        $duration = isset($data['duration']) ? $data['duration'] : 1;
        $onlyEmployeeId = isset($data['only_employee']['id']) ? $data['only_employee']['id'] : null;
        $jobActivity = JobActivity::findOrFail($jobActivityId);
        $taskBuild = TaskFactory::build($jobActivity->description);
        $responsibles = $taskBuild->getResponsibleList();

        if( ! is_null($onlyEmployeeId) ) {
            $responsibles = $responsibles->filter(function($responsible) use ($onlyEmployeeId) {
                return $responsible->id == $onlyEmployeeId;
            });
        }

        $iniDate = isset($data['iniDate']) ? new DateTime($data['iniDate']) : null;
        $finDate = isset($data['finDate']) ? new DateTime($data['finDate']) : null;

        if(DateHelper::dateInPast($iniDate, new DateTime('now'))) {
            $iniDate = new DateTime('now');
        }

        $arr = [];
        while($iniDate->format('Y-m-d') <= $finDate->format('Y-m-d')) {
            $arr[] = ActivityHelper::calculateNextDate($iniDate->format('Y-m-d'), $jobActivity, $responsibles, $duration);
            $iniDate = DateHelper::nextUtil($iniDate, 1);
        }
        
        return [
            'dates' => $arr,
            'responsibles' => $taskBuild->getResponsibleList()
        ];
    }

    public static function editAvailableDate(array $data)
    {
        if( isset($data['task1']['id']) && isset($data['task2']['id']) ) {
            $task1 = (object) $data['task1'];
            $task2 = (object) $data['task2'];
            ActivityHelper::swapActivities($task1, $task2);
        }
        else {
            ActivityHelper::moveActivity($data['task1'], $data['task2']);
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
            $firstDate = $taskIsThisDate->shift();
            $duration .= -$firstDate->task_duration;
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

    public function deleteItems()
    {
        $this->items()->delete();
    }

    public static function edit(array $data)
    {
        $id = $data['id'];
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        //$job_id = isset($data['job']['id']) ? $data['job']['id'] : null;
        //$job_activity_id = isset($data['job_activity']['id']) ? $data['job_activity']['id'] : null;
        $task = Task::find($id);

        $task->update(
            array_merge($data, [
                'responsible_id' => $responsible_id,
                //'job_id' => $job_id,
                //'job_activity_id' => $job_activity_id
            ])
        );

        $task->deleteItems();
        $task->saveItems();

        return $task;
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
            'pagination' => [
                'data' => $result,
                'total' => $total,
                'page' => $page
            ],
            'updatedInfo' => Task::updatedInfo()
        ];
    }
    

    public static function updatedInfo() {
        $lastData = Task::orderBy('updated_at', 'desc')->limit(1)->first();

        if($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->updated_at))->format('d/m/Y H:i:s'),
            'employee' => $lastData->job->attendance->name
        ];
    }

    public static function filterMyTask($params)
    {
        $user = User::logged();
        
        $iniDate = isset($params['iniDate']) ? $params['iniDate'] : null;
        $finDate = isset($params['finDate']) ? $params['finDate'] : null;
        $paginate = isset($params['paginate']) ? $params['paginate'] : true;

        $tasks = Task::select('task.*')
        ->leftJoin('job', 'job.id', '=', 'task.job_id')
        ->where(function($query) use ($user) {
            $query->where('job.attendance_id', '=', $user->employee->id);
            $query->orWhere('task.responsible_id', '=', $user->employee->id);
        });

        if (!is_null($iniDate) && !is_null($finDate)) {
            $sql = '(task.available_date >= "' . $iniDate . '"';
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
                $task->responsible;
                $task->items;
                $task->job_activity;
            }

            $total = $tasks->count();
            $page = 0;
        }

        return [
            'pagination' => [
                'data' => $result,
                'total' => $total,
                'page' => $page
            ],
            'updatedInfo' => Task::updatedInfo()
        ];
    }

    public static function remove($id)
    {
        $task = Task::find($id);
        $task->items()->delete();
        $task->delete();
    }

    public static function removeMyTask($id)
    {
        $task = Task::find($id);

        if($task->job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para remover esse job.');
        }

        $task->items()->delete();
        $task->delete();
    }

    public static function get($id)
    {
        $task = Task::findOrFail($id);
        $task->responsible;
        $task->job;
        $task->items;
        $task->job_activity;
        return $task;
    }

    public function type(): TaskInterface {
        return TaskFactory::build($this->job_activity->description);
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

    public function setAvailableDateAttribute($value) {
        $this->attributes['available_date'] = substr($value, 0, 10);
    }
}
