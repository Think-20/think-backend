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
        'reopened', 'task_id', 'project_file_done'
    ];

    public function getTaskName() {
        $pad = $this->reopened > 0 ? str_pad($this->reopened, 2, '0', \STR_PAD_LEFT) : '';
        return trim($this->job_activity->description . ' ' . $pad);
    }

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

            $message1 = 'Mudança de agenda de ' . $task1->getTaskName() . ' da ';
            $message1 .= $task1->job->getJobName();
            $message1 .= ' para ' . (new DateTime($task1->available_date))->format('d/m/Y') . ' para ' . $task1->responsible->name;

            $message2 = 'Mudança de agenda de ' . $task2->getTaskName() . ' da ';
            $message2 .= $task2->job->getJobName();
            $message2 .= ' para ' . (new DateTime($task2->available_date))->format('d/m/Y') . ' para ' . $task2->responsible->name;

            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message1
            ], NotificationSpecial::createMulti([
                'user_id' => $task1->responsible->user->id,
                'message' => $message1
            ], [
                'user_id' => $task1->job->attendance->user->id,
                'message' => $message1
            ]), 'Alteração de tarefa', $task1->id);

            Notification::createAndNotify(User::logged()->employee, [
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

            $message = 'Mudança de agenda de ' . $task->getTaskName() . ' da ';
            $message .= $task->job->getJobName();
            $message .= ' para ' . (new DateTime($task->available_date))->format('d/m/Y') . ' para ' . $task->responsible->name;

            Notification::createAndNotify(User::logged()->employee, [
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

    public function updateProjectFileDone() {
        if($this->project_files->count() > 0) {
            $this->project_file_done = 1;
        } else {
            $this->project_file_done = 0;
        }

        $this->save();
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

            $message1 = 'Mudança de agenda de ' . $task1->job_activity->description . ' da ';
            $message1 .= $task1->getTaskName();
            $message1 .= ' para ' . (new DateTime($task1->available_date))->format('d/m/Y') . ' para ' . $task1->responsible->name;

            $message2 = 'Mudança de agenda de ' . $task2->job_activity->description . ' da ';
            $message2 .= $task2->getTaskName();
            $message2 .= ' para ' . (new DateTime($task2->available_date))->format('d/m/Y') . ' para ' . $task2->responsible->name;

            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message1
            ], NotificationSpecial::createMulti([
                'user_id' => $task1->responsible->user->id,
                'message' => $message1
            ], [
                'user_id' => $task1->job->attendance->user->id,
                'message' => $message1
            ]), 'Alteração de tarefa', $task1->id);

            Notification::createAndNotify(User::logged()->employee, [
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

            $message = 'Mudança de agenda de ' . $task->getTaskName() . ' da ';
            $message .= $task->job->getJobName();
            $message .= ' para ' . (new DateTime($task->available_date))->format('d/m/Y') . ' para ' . $task->responsible->name;

            Notification::createAndNotify(User::logged()->employee, [
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

    public function insertContinuation(DateTime $date, $duration) {
        $availableDate = $date->format('Y-m-d');
        $jobActivity = JobActivity::where('description', '=', 'Continuação')->first();

        $data = [
            'responsible' => ['id' => $this->responsible_id],
            'job' => ['id' => $this->job->id],
            'job_activity' => ['id' => $jobActivity->id],
            'duration' => $duration,
            'available_date' => $availableDate,
            'task' => ['id' => $this->id]
        ];
        
        Task::insert($data);
    }

    public function insertMemorial() {
        $date = DateHelper::nextUtilIfNotUtil(DateHelper::nextUtil(new DateTime('now'), 1))->format('Y-m-d');
        $jobActivity = JobActivity::where('description', '=', 'Memorial descritivo')->first();

        $count = Task::where('job_activity_id', '=', $jobActivity->id)
        ->where('task_id', '=', $this->id)
        ->get()
        ->count();

        if($count > 0) {
            return;
        }

        $data = [
            'responsible' => ['id' => $this->job->attendance_id],
            'job' => ['id' => $this->job->id],
            'job_activity' => ['id' => $jobActivity->id],
            'duration' => 1,
            'available_date' => $date,
            'task' => ['id' => $this->id]
        ];
        
        Task::insert($data, $this->job->attendance, false);
    }

    public static function insert(array $data, NotifierInterface $notifier = null, $recursiveScheduleBlock = true)
    {
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        $job_id = isset($data['job']['id']) ? $data['job']['id'] : null;
        $job_activity_id = isset($data['job_activity']['id']) ? $data['job_activity']['id'] : null;
        $task_id = isset($data['task']['id']) ? $data['task']['id'] : null;
        $verifyScheduleBlock = isset($data['verify_schedule_block']) ? $data['verify_schedule_block'] : true;

        $task = new Task(array_merge($data, [
            'responsible_id' => $responsible_id,
            'job_id' => $job_id,
            'job_activity_id' => $job_activity_id,
            'task_id' => $task_id,
        ]));
        
        if($verifyScheduleBlock && $recursiveScheduleBlock) {
            Task::checkScheduleBlock($task->available_date, true);
        }

        $task->save();
        $task->saveItems($verifyScheduleBlock);

        $message = $task->getTaskName() . ' da ';
        $message .= $task->job->getJobName();
        $message .= ' agendado em ' . (new DateTime($task->available_date))->format('d/m/Y') . ' para ' . $task->responsible->name;

        if($notifier == null) {
            $notifier = User::logged()->employee;
        }

        Notification::createAndNotify($notifier, [
            'message' => $message
        ], NotificationSpecial::createMulti([
            'user_id' => $task->responsible->user->id,
            'message' => $message
        ], [
            'user_id' => $task->job->attendance->user->id,
            'message' => $message
        ]), 'Cadastro de tarefa', $task->id);

        Task::modifyReopened($task);

        return $task;
    }

    public static function checkScheduleBlock($availableDate, $exception = false) {
        $blocked = ScheduleBlock::where('date', '=', $availableDate)->first() != null;

        if(!$blocked) return false;
        if($exception) throw new \Exception('Você não pode agendar nesta data, está bloqueada.');

        return true;
    }

    public static function modifyReopened(Task $task) {
        $description = $task->job_activity->description;

        if(in_array($description, ['Projeto', 'Outsider'])) {
            $sum = 0;
        } else {
            $sum = 1;    
        }
        
        foreach($task->job->tasks as $t) {
            if($t->job_activity->description == $description) {
                $t->reopened = $sum;
                $t->save();
                $sum++;
            }
        }
    }

    public function saveItems($verifyScheduleBlock)
    {
        $date = new DateTime($this->available_date);
        $duration = $this->duration;
        $tempDuration = (float) $duration;

        for ($i = 0; $i < $duration; $i++) {
            if($verifyScheduleBlock && Task::checkScheduleBlock($date)) {
                $date = DateHelper::sumUtil($date, 1);
                $this->duration = $duration - $tempDuration;
                $this->save();
                $this->insertContinuation($date, $tempDuration);
                return;
            }

            $fator = (float) $tempDuration >= 1
                ? 1
                : $tempDuration;

            $tempDuration = (float) $tempDuration - $fator;
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
        $oldDate = $task->available_date;

        $task->update(
            array_merge($data, [
                'responsible_id' => $responsible_id,
            ])
        );

        $task = Task::find($id);
        $task->deleteItems();
        $task->saveItems();

        if($oldResponsibleId != $task->responsible_id) {
            $message = 'Responsável de ' . strtolower($task->getTaskName()) . ' da ';
            $message .= $task->job->getJobName();
            $message .= ' alterado de ' . $oldResponsible . ' para ' . $task->responsible->name; 

            Notification::createAndNotify(User::logged()->employee, [
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
            $message = 'Duração de ' . strtolower($task->getTaskName()) . ' da ';
            $message .= $task->job->getJobName();
            $message .= ' alterada de ' . ((int) $oldDuration) . ' para ' . ((int) $task->duration) . ' dia(s)'; 

            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message
            ], NotificationSpecial::createMulti([
                'user_id' => $task->responsible->user->id,
                'message' => $message,
            ], [
                'user_id' => $task->job->attendance->user->id,
                'message' => $message
            ]), 'Alteração de tarefa', $task->id);
        }

        if($oldDate != $task->available_date) {
            $message = 'Data de ' . strtolower($task->getTaskName()) . ' da ';
            $message .= $task->job->getJobName();
            $message .= ' alterada de ' . (new DateTime($oldDate))->format('d/m/Y') . ' para ' . (new DateTime($task->available_date))->format('d/m/Y');

            Notification::createAndNotify(User::logged()->employee, [
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

        $status = isset($params['status']) ? $params['status'] : null;
        $clientName = isset($params['clientName']) ? $params['clientName'] : null;
        $attendanceId = isset($params['attendance']['id']) ? $params['attendance']['id'] : null;
        $jobTypeId = isset($params['job_type']['id']) ? $params['job_type']['id'] : null;
        $jobActivityId = isset($params['job_activity']['id']) ? $params['job_activity']['id'] : null;
        $creationId = isset($params['creation']['id']) ? $params['creation']['id'] : null;
        $responsibleId = isset($params['responsible']['id']) ? $params['responsible']['id'] : null;

        $statusArrayId = isset($params['status_array']) && !empty($params['status_array']) ? array_map(function($v) {
            return $v['id'];
        }, $params['status_array']) : null;
        $attendanceArrayId = isset($params['attendance_array']) && !empty($params['attendance_array']) ? array_map(function($v) {
            return $v['id'];
        }, $params['attendance_array']) : null;
        $jobTypeArrayId = isset($params['job_type_array']) && !empty($params['job_type_array']) ? array_map(function($v) {
            return $v['id'];
        }, $params['job_type_array']) : null;
        $jobActivityArrayId = isset($params['job_activity_array']) && !empty($params['job_activity_array']) ? array_map(function($v) {
            return $v['id'];
        }, $params['job_activity_array']) : null;
        $responsibleArrayId = isset($params['responsible_array']) && !empty($params['responsible_array']) ? array_map(function($v) {
            return $v['id'];
        }, $params['responsible_array']) : null;

        $tasks = Task::with(
            'items', 'responsible', 'job_activity', 'job', 'job.client', 'job.job_type', 
            'job.status', 'job.agency', 'job.attendance', 'job.job_activity', 'task', 'task.job_activity'
        );

        if (!is_null($iniDate) && !is_null($finDate)) {
            $sql = '(task.available_date >= "' . $iniDate . '"';
            $sql .= ' AND task.available_date <= "' . $finDate . '")';
            $tasks->whereRaw($sql);
        }

        if( ! is_null($attendanceArrayId) ) {
            $tasks->whereHas('job.attendance', function($query) use ($attendanceArrayId) {
                $query->whereIn('id', $attendanceArrayId);
            });   
        }

        if( ! is_null($jobTypeArrayId) ) {
            $tasks->whereHas('job.job_type', function($query) use ($jobTypeArrayId) {
                $query->whereIn('id', $jobTypeArrayId);
            });   
        }

        if( ! is_null($statusArrayId) ) {
            $tasks->whereHas('job', function($query) use ($statusArrayId) {
                $query->whereIn('status_id', $statusArrayId);
            });   
        }

        if( ! is_null($jobActivityArrayId) ) {
            $tasks->whereIn('job_activity_id', $jobActivityArrayId);
        }

        if( ! is_null($responsibleArrayId) ) {
            $tasks->whereIn('responsible_id', $responsibleArrayId);
        }

        if ( ! is_null($clientName) ) {
            $tasks->whereHas('job.client', function($query) use ($clientName) {
                $query->where('fantasy_name', 'LIKE', '%' . $clientName . '%');
                $query->orWhere('name', 'LIKE', '%' . $clientName . '%');
            });  
            $tasks->orWhereHas('job', function($query) use ($clientName) {
                $query->where('not_client', 'LIKE', '%' . $clientName . '%');
            });        
        }

        if ( ! is_null($attendanceId) ) {
            $tasks->whereHas('job.attendance', function($query) use ($attendanceId) {
                $query->where('id', '=', $attendanceId);
            });         
        }

        if ( ! is_null($jobTypeId) ) {
            $tasks->whereHas('job.job_type', function($query) use ($jobTypeId) {
                $query->where('id', '=', $jobTypeId);
            });         
        }

        if ( ! is_null($jobActivityId) ) {
            $tasks->whereHas('job.job_activity', function($query) use ($jobActivityId) {
                $query->where('id', '=', $jobActivityId);
            });         
        }

        if ( ! is_null($creationId) ) {
            $tasks->where('responsible_id', '=', $creationId);      
        }

        if ( ! is_null($responsibleId) ) {
            $tasks->where('responsible_id', '=', $responsibleId);      
        }

        if( ! is_null($status) ) {
            $tasks->whereHas('job', function($query) use ($status) {
                $query->where('status_id', '=', $status);
            });
        }

        $tasks->orderBy('task.available_date', 'ASC');

        if ($paginate) {
            $paginate = $tasks->paginate(50);
            $result = $paginate->items();
            $page = $paginate->currentPage();
            $total = $paginate->total();
        } else {
            $result = $tasks->get();
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
        $childs = Task::where('task_id', '=', $task->id)->get();
        
        if($childs->count() > 0) {
            throw new \Exception('Por favor, delete a tarefa anterior primeiro.');
        }

        $oldTask = clone $task;
        
        $message = $task->getTaskName() . ' de ';
        $message .= $task->job->getJobName();
        $message .= ' removido';

        Notification::createAndNotify(User::logged()->employee, [
            'message' => $message
        ], NotificationSpecial::createMulti([
            'user_id' => $task->responsible->user->id,
            'message' => $message,
        ], [
            'user_id' => $task->job->attendance->user->id,
            'message' => $message
        ]), 'Deleção de tarefa', $task->id);

        $task->items()->delete();
        $task->delete();
        
        Task::modifyReopened($oldTask);
    }

    public static function removeMyTask($id)
    {
        $task = Task::find($id);
        $oldTask = clone $task;
        $childs = Task::where('task_id', '=', $task->id)->get();
        
        if($childs->count() > 0) {
            throw new \Exception('Por favor, delete a tarefa anterior primeiro.');
        }

        if($task->job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para remover esse job.');
        }


        $message = $task->getTaskName() . ' de ';
        $message .= $task->job->getJobName();
        $message .= ' removido';

        Notification::createAndNotify(User::logged()->employee, [
            'message' => $message
        ], NotificationSpecial::createMulti([
            'user_id' => $task->responsible->user->id,
            'message' => $message,
        ], [
            'user_id' => $task->job->attendance->user->id,
            'message' => $message
        ]), 'Deleção de tarefa', $task->id);

        $task->items()->delete();
        $task->delete();
        
        Task::modifyReopened($oldTask);
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

    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id');
    }

    public function budget()
    {
        return $this->hasOne('App\Budget', 'task_id');
    }

    public function project_files()
    {
        return $this->hasMany('App\ProjectFile', 'task_id');
    }

    public function setDurationAttribute($value)
    {
        $this->attributes['duration'] = (float)str_replace(',', '.', $value);
    }

    public function setAvailableDateAttribute($value) {
        $this->attributes['available_date'] = substr($value, 0, 10);
    }
}
