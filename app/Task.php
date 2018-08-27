<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DateTime;
use DateInterval;

class Task extends Model
{
    protected $table = 'task';
    protected $fillable = [
        'job_id', 'responsible_id', 'available_date', 'job_activity_id', 'duration',
        'reopened'
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
        $task = null;
        
        if( isset($data['task1']['id']) && isset($data['task2']['id']) ) {
            $oTask1 = (object) $data['task1'];
            $oTask2 = (object) $data['task2'];
            $task1 = Task::find($oTask1->id);
            $task2 = Task::find($oTask2->id);
            ActivityHelper::swapActivities($task1, $task2);

            $message1 = $task1->job_activity->description . ' de ';
            $message1 .= ($task1->job->client ? $task1->job->client->fantasy_name : $task1->job->not_client);
            $message1 .= ' alterado para ' . (new DateTime($task1->available_date))->format('d/m/Y');

            $message2 = $task2->job_activity->description . ' de ';
            $message2 .= ($task2->job->client ? $task2->job->client->fantasy_name : $task2->job->not_client);
            $message2 .= ' alterado para ' . (new DateTime($task2->available_date))->format('d/m/Y');

            Notification::createAndNotify(User::logged(), [
                'message' => $message1
            ], NotificationSpecial::createMulti([
                'user_id' => $task1->responsible->user->id,
                'message' => $message1
            ], [
                'user_id' => $task1->job->attendance->user->id,
                'message' => $message1
            ]), 'Alteração de tarefa', $task1->id);

            Notification::createAndNotify(User::logged(), [
                'message' => $message2
            ], NotificationSpecial::createMulti([
                'user_id' => $task2->responsible->user->id,
                'message' => $message2
            ], [
                'user_id' => $task2->job->attendance->user->id,
                'message' => $message2
            ]), 'Alteração de tarefa', $task2->id);
        }
        else {
            $task = ActivityHelper::moveActivity($data['task1'], $data['task2']);

            $message = $task->job_activity->description . ' de ';
            $message .= ($task->job->client ? $task->job->client->fantasy_name : $task->job->not_client);
            $message .= ' alterado para ' . (new DateTime($task->available_date))->format('d/m/Y');

            Notification::createAndNotify(User::logged(), [
                'message' => $message
            ], NotificationSpecial::createMulti([
                'user_id' => $task->responsible->user->id,
                'message' => $message
            ], [
                'user_id' => $task->job->attendance->user->id,
                'message' => $message
            ]), 'Alteração de tarefa', $task->id);
        }        
        
        return true;
    }

    public static function myEditAvailableDate(array $data)
    {
        $task = null;
        
        if( isset($data['task1']['id']) && isset($data['task2']['id']) ) {
            $oTask1 = (object) $data['task1'];
            $oTask2 = (object) $data['task2'];
            $task1 = Task::find($oTask1->id);
            $task2 = Task::find($oTask2->id);

            if(($task1->job->attendance_id != $task2->job->attendance_id) || $task1->job->attendance_id != User::logged()->employee->id) {
                throw new \Exception('Você não pode alterar uma tarefa que não pertence a você.');
            }

            ActivityHelper::swapActivities($task1, $task2);

            $message1 = $task1->job_activity->description . ' de ';
            $message1 .= ($task1->job->client ? $task1->job->client->fantasy_name : $task1->job->not_client);
            $message1 .= ' alterado para ' . (new DateTime($task1->available_date))->format('d/m/Y');

            $message2 = $task2->job_activity->description . ' de ';
            $message2 .= ($task2->job->client ? $task2->job->client->fantasy_name : $task2->job->not_client);
            $message2 .= ' alterado para ' . (new DateTime($task2->available_date))->format('d/m/Y');

            Notification::createAndNotify(User::logged(), [
                'message' => $message1
            ], NotificationSpecial::createMulti([
                'user_id' => $task1->responsible->user->id,
                'message' => $message1
            ], [
                'user_id' => $task1->job->attendance->user->id,
                'message' => $message1
            ]), 'Alteração de tarefa', $task1->id);

            Notification::createAndNotify(User::logged(), [
                'message' => $message2
            ], NotificationSpecial::createMulti([
                'user_id' => $task2->responsible->user->id,
                'message' => $message2
            ], [
                'user_id' => $task2->job->attendance->user->id,
                'message' => $message2
            ]), 'Alteração de tarefa', $task2->id);
        }
        else {
            $task = ActivityHelper::moveActivity($data['task1'], $data['task2']);

            if($task->job->attendance_id != User::logged()->employee->id) {
                throw new \Exception('Você não pode alterar uma tarefa que não pertence a você.');
            }

            $message = $task->job_activity->description . ' de ';
            $message .= ($task->job->client ? $task->job->client->fantasy_name : $task->job->not_client);
            $message .= ' alterado para ' . (new DateTime($task->available_date))->format('d/m/Y');

            Notification::createAndNotify(User::logged(), [
                'message' => $message
            ], NotificationSpecial::createMulti([
                'user_id' => $task->responsible->user->id,
                'message' => $message
            ], [
                'user_id' => $task->job->attendance->user->id,
                'message' => $message
            ]), 'Alteração de tarefa', $task->id);
        }        
        
        return true;
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

        $message = ($task->job_activity->description) . ' de ';
        $message .= ($task->job->client ? $task->job->client->fantasy_name : $task->job->not_client);
        $message .= ' cadastrado para ' . (new DateTime($task->available_date))->format('d/m/Y');

        Notification::createAndNotify(User::logged(), [
            'message' => $message
        ], NotificationSpecial::createMulti([
            'user_id' => $task->responsible->user->id,
            'message' => $message
        ], [
            'user_id' => $task->job->attendance->user->id,
            'message' => $message
        ]), 'Cadastro de tarefa', $task->id);

        $task->modifyReopened(1);

        return $task;
    }

    public function modifyReopened($inc) {
        if($this->job_activity->description != 'Modificação') return;

        $sum = 0;
        foreach($this->job->tasks as $task) {
            if($task->job_activity->description == 'Modificação') {
                $sum++;
            }
        }

        $this->reopened = $sum + $inc;
        $this->save();
    }

    public function saveItems()
    {
        $date = new DateTime($this->available_date);
        /*
        $taskIsThisDate = TaskItem::select('*', 'task_item.duration as task_duration')
            ->leftJoin('task', 'task.id', '=', 'task_item.task_id')
            ->where('responsible_id', '=', $this->responsible->id)
            ->where('available_date', '=', $this->available_date)
            ->get();
        */

        $duration = $this->duration;
        /*
        if ($taskIsThisDate->count() > 0) {
            $firstDate = $taskIsThisDate->shift();
            $duration .= -$firstDate->task_duration;

            if($duration > 0) {
                TaskItem::insert([
                    'duration' => $this->duration - $duration,
                    'date' => $date->format('Y-m-d'),
                    'task_id' => $this->id
                ]);
                $date = DateHelper::sumUtil($date, 1);
            }            
        }
        */

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
        $oldDuration = $task->duration;
        $oldResponsible = $task->responsible->name;
        $oldResponsibleId = $task->responsible->id;
        $oldDuration = $task->duration;

        $task->update(
            array_merge($data, [
                'responsible_id' => $responsible_id,
            ])
        );

        $task->deleteItems();
        $task->saveItems();

        if($oldResponsible != $task->responsible->name) {
            $message = 'Responsável de ' . strtolower($task->job_activity->description) . ' pertencente a ';
            $message .= ($task->job->client ? $task->job->client->fantasy_name : $task->job->not_client);
            $message .= ' alterado de ' . $oldResponsible . ' para ' . $task->responsible->name; 

            Notification::createAndNotify(User::logged(), [
                'message' => $message
            ], NotificationSpecial::createMulti([
                'user_id' => $task->responsible->user->id,
                'message' => $message,
            ], [
                'user_id' => $oldResponsibleId,
                'message' => $message
            ], [
                'user_id' => $task->job->attendance->user->id,
                'message' => $message
            ]), 'Alteração de tarefa', $task->id);
        }

        if($oldDuration != $task->duration) {
            $message = 'A duração de ' . strtolower($task->job_activity->description) . ' pertencente a ';
            $message .= ($task->job->client ? $task->job->client->fantasy_name : $task->job->not_client);
            $message .= ' alterado de ' . ((int) $oldDuration) . ' para ' . ((int) $task->duration) . ' dia(s)'; 

            Notification::createAndNotify(User::logged(), [
                'message' => $message
            ], NotificationSpecial::createMulti([
                'user_id' => $task->responsible->user->id,
                'message' => $message,
            ], [
                'user_id' => $task->job->attendance->user->id,
                'message' => $message
            ]), 'Alteração de tarefa', $task->id);
        }

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
        $typeIds = NotificationType::where('description', 'LIKE', '%tarefa%')->get();
        $lastData = Notification::with('user', 'notifier')
        ->whereIn('type_id', $typeIds->map(function($type) { return $type->id; }))
        ->orderBy('date', 'desc')
        ->limit(1)
        ->first();

        if($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->date))->format('d/m/Y H:i:s'),
            'employee' => $lastData->notifier->getName()
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
        
        $message = $task->job_activity->description . ' pertencente a ';
        $message .= ($task->job->client ? $task->job->client->fantasy_name : $task->job->not_client);
        $message .= ' removido';

        Notification::createAndNotify(User::logged(), [
            'message' => $message
        ], NotificationSpecial::createMulti([
            'user_id' => $task->responsible->user->id,
            'message' => $message,
        ], [
            'user_id' => $task->job->attendance->user->id,
            'message' => $message
        ]), 'Deleção de tarefa', $task->id);

        $task->items()->delete();
        $task->modifyReopened(-1);
        $task->delete();
    }

    public static function removeMyTask($id)
    {
        $task = Task::find($id);

        if($task->job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para remover esse job.');
        }

        $message = 'A tarefa ' . strtolower($task->job_activity->description) . ' pertencente a ';
        $message .= ($task->job->client ? $task->job->client->fantasy_name : $task->job->not_client);
        $message .= ' foi removida';

        Notification::createAndNotify(User::logged(), [
            'message' => $message
        ], NotificationSpecial::createMulti([
            'user_id' => $task->responsible->user->id,
            'message' => $message,
        ], [
            'user_id' => $task->job->attendance->user->id,
            'message' => $message
        ]), 'Deleção de tarefa', $task->id);

        $task->items()->delete();
        $task->modifyReopened(-1);
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
