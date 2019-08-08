<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DateTime;

class Task extends Model
{
    protected $table = 'task';
    protected $fillable = [
        'job_id', 'responsible_id', 'job_activity_id', 'duration',
        'reopened', 'task_id', 'done'
    ];

    public function getTaskName() {
        $pad = $this->reopened > 0 ? str_pad($this->reopened, 2, '0', \STR_PAD_LEFT) : '';
        return trim($this->job_activity->description . ' ' . $pad);
    }

    public static function responsiblesByActivity($jobActivityId) {
        $jobActivity = JobActivity::find($jobActivityId);
        return $jobActivity->responsibles;
    }

    public static function getNextAvailableDate(string $availableDate, string $jobActivity)
    {
        $initialDate = new DateTime($availableDate);
        $finalDate = DateHelper::sumUtil($initialDate, 30);
        $jobActivity = JobActivity::where('description', '=', $jobActivity)->first();

        return [
            'items' => TaskHelper::getNextAvailableDate($initialDate, $finalDate, $jobActivity),
            'responsibles' => $jobActivity->responsibles
        ]; 
    }

    public static function getNextAvailableDates(array $data)
    {
        $jobActivityId = isset($data['job_activity']['id']) ? $data['job_activity']['id'] : null;
        $onlyEmployees = isset($data['only_employee']['id']) ? [ $data['only_employee']['id'] ] : [];

        $jobActivity = JobActivity::find($jobActivityId);
        $initialDate = isset($data['initialDate']) ? new DateTime($data['initialDate']) : null;
        $finalDate = isset($data['finalDate']) ? new DateTime($data['finalDate']) : DateHelper::sumUtil($initialDate, 30);

        return [
            'items' => TaskHelper::getDates($initialDate, $finalDate, $jobActivity, $onlyEmployees),
            'responsibles' => $jobActivity->responsibles
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

    public function insertContinuation(DateTime $date, $duration, $tempBudget) {
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
        $date = ScheduleBlock::sumUtilNonBlocked(DateHelper::subUtil(new DateTime('now'), 1), $this->job->attendance->user, 1);
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
            'available_date' => $date->format('Y-m-d'),
            'task' => ['id' => $this->id]
        ];
        
        Task::insert($data, $this->job->attendance, false);
    }

    public function insertBudget() {
        $date = ScheduleBlock::sumUtilNonBlocked(DateHelper::subUtil(new DateTime('now'), 1), $this->job->attendance->user, 1);
        $arr = Task::getNextAvailableDate($date->format('Y-m-d'), 1, 'Orçamento', $this->job->budget_value);       

        $jobActivity = JobActivity::where('description', '=', 'Orçamento')->first();
        $count = Task::where('job_activity_id', '=', $jobActivity->id)
        ->where('task_id', '=', $this->id)
        ->get()
        ->count();

        if($count > 0) {
            return;
        }

        $responsible = $arr['available_responsibles'][0];
        $data = [
            'responsible' => ['id' => $responsible->id],
            'job' => ['id' => $this->job->id],
            'job_activity' => ['id' => $jobActivity->id],
            'duration' => 1,
            'available_date' => $arr['available_date'],
            'task' => ['id' => $this->id]
        ];
        
        Task::insert($data, $responsible, false);
    }

    public function insertBudgetModify() {
        $date = ScheduleBlock::sumUtilNonBlocked(DateHelper::subUtil(new DateTime('now'), 1), $this->job->attendance->user, 1);
        $arr = Task::getNextAvailableDate($date->format('Y-m-d'), 1, 'Modificação de orçamento', $this->job->budget_value);       

        $jobActivity = JobActivity::where('description', '=', 'Modificação de orçamento')->first();
        $count = Task::where('job_activity_id', '=', $jobActivity->id)
        ->where('task_id', '=', $this->id)
        ->get()
        ->count();

        if($count > 0) {
            return;
        }

        $responsible = $arr['available_responsibles'][0];
        $data = [
            'responsible' => ['id' => $responsible->id],
            'job' => ['id' => $this->job->id],
            'job_activity' => ['id' => $jobActivity->id],
            'duration' => 1,
            'available_date' => $arr['available_date'],
            'task' => ['id' => $this->id]
        ];
        
        Task::insert($data, $responsible, false);
    }

    public static function insert(array $data, NotifierInterface $notifier = null, $recursiveScheduleBlock = true)
    {
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        $job_id = isset($data['job']['id']) ? $data['job']['id'] : null;
        $job_activity_id = isset($data['job_activity']['id']) ? $data['job_activity']['id'] : null;
        $task_id = isset($data['task']['id']) ? $data['task']['id'] : null;
        $items = isset($data['task']['items']) ? $data['task']['items'] : [];

        $task = new Task(array_merge($data, [
            'responsible_id' => $responsible_id,
            'job_id' => $job_id,
            'job_activity_id' => $job_activity_id,
            'task_id' => $task_id,
        ]));

        $task->save();
        TaskItem::insertAll($task, $items);

        if($task->initialDate() != null) {
            $message = $task->getTaskName() . ' da ';
            $message .= $task->job->getJobName();
            $message .= ' agendado em ' . (new DateTime($task->initialDate()))->format('d/m/Y') . ' para ' . $task->responsible->name;

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
        }

        Task::modifyReopened($task);
        return $task;
    }

    public function initialDate() {
        return $this->items->count() == 0 ? null : $this->items->first()->date;
    }

    public static function checkScheduleBlock(string $availableDate, Employee $responsible, $exception = false) {
        $blocked = ScheduleBlock::checkIfBlocked($availableDate, $responsible->user->id);

        if(!$blocked) return false;
        if($exception) throw new \Exception('Você não pode agendar nesta data, está bloqueada.');

        return true;
    }

    public static function modifyReopened(Task $task) {
        if($task->job_activity->counter == 0) {
            return;
        }

        $sum = 1;    
        
        foreach($task->job->tasks as $t) {
            if($t->job_activity->description == $task->job_activity->description) {
                $t->reopened = $sum;
                $t->save();
                $sum++;
            }
        }
    }

    public function calcDurationBudget(): array {
        $taskBudget = new TaskBudget();
        $responsible = $this->responsible;
        $jobValue = $this->job->budget_value;
        $duration = 0;
        $durationArray = [];
        $date = new DateTime($this->initialDate());

        while($jobValue > 0) {
            $space = $taskBudget->quantityAvailable($date, $responsible);
            $diff = $jobValue - $space;
            $durationArray[$duration] = $diff <= 0 ? $jobValue : $space;
            $jobValue = $jobValue - $space;

            $duration++;
            $date = ScheduleBlock::sumUtilNonBlocked($date, $responsible->user, 1);
        }
        
        return $durationArray;
    }

    public function saveItems() {
        
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
        $oldDate = $task->initialDate();

        $task->update(
            array_merge($data, [
                'responsible_id' => $responsible_id,
            ])
        );

        $task = Task::find($id);
        $task->deleteItems();

        //Rotina para burlar a edição via ADM para orçamento que tem duração por valor
        if($task->job_activity->description == 'Orçamento') {
            $task->job_activity_id = 1;
            $task->save();
            $task = Task::find($id);
            $task->saveItems(true);
            $task->job_activity_id = 2;
            $task->save();
            $task = Task::find($id);
        } else {
            $task->saveItems(true);            
        }


        if($oldResponsibleId != $task->responsible_id) {
            $message = 'Responsável de ' . mb_strtolower($task->getTaskName()) . ' da ';
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
            $message = 'Duração de ' . mb_strtolower($task->getTaskName()) . ' da ';
            $message .= $task->job->getJobName();
            $message .= ' alterada de ' . strval((int) $oldDuration) . ' para ' . strval((int) $task->duration) . ' dia(s)'; 

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

        if($oldDate != $task->initialDate()) {
            $message = 'Data de ' . mb_strtolower($task->getTaskName()) . ' da ';
            $message .= $task->job->getJobName();
            $message .= ' alterada de ' . (new DateTime($oldDate))->format('d/m/Y') . ' para ' . (new DateTime($task->initialDate()))->format('d/m/Y');

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
        $departmentArrayId = isset($params['department_array']) && !empty($params['department_array']) ? array_map(function($v) {
            return $v['id'];
        }, $params['department_array']) : null;

        $taskItems = TaskItem::with(
            'task', 'task.responsible', 'task.job_activity', 'task.job', 'task.job.client', 'task.job.job_type', 
            'task.job.status', 'task.job.agency', 'task.job.attendance', 'task.job.job_activity', 'task.task', 
            'task.task.job_activity', 'task.items'
        );

        if (!is_null($iniDate) && !is_null($finDate)) {
            $taskItems->where('task_item.date', '>=', $iniDate);
            $taskItems->where('task_item.date', '<=', $finDate);
        }

        if( ! is_null($attendanceArrayId) ) {
            $taskItems->whereHas('task.job.attendance', function($query) use ($attendanceArrayId) {
                $query->whereIn('id', $attendanceArrayId);
            });   
        }

        if( ! is_null($jobTypeArrayId) ) {
            $taskItems->whereHas('task.job.job_type', function($query) use ($jobTypeArrayId) {
                $query->whereIn('id', $jobTypeArrayId);
            });   
        }

        if( ! is_null($statusArrayId) ) {
            $taskItems->whereHas('task.job', function($query) use ($statusArrayId) {
                $query->whereIn('status_id', $statusArrayId);
            });   
        }

        if( ! is_null($jobActivityArrayId) ) {
            $taskItems->whereIn('task.job_activity_id', $jobActivityArrayId);
        }

        if( ! is_null($responsibleArrayId) ) {
            $taskItems->whereIn('task.responsible_id', $responsibleArrayId);
        }

        if( ! is_null($departmentArrayId) ) {
            $taskItems->whereHas('task.responsible', function($query) use ($departmentArrayId) {
                $query->whereIn('department_id', $departmentArrayId);
            });
        }

        if ( ! is_null($clientName) ) {
            $taskItems->whereHas('task.job.client', function($query) use ($clientName) {
                $query->where('fantasy_name', 'LIKE', '%' . $clientName . '%');
                $query->orWhere('name', 'LIKE', '%' . $clientName . '%');
            });  
            $taskItems->orWhereHas('task.job', function($query) use ($clientName) {
                $query->where('not_client', 'LIKE', '%' . $clientName . '%');
            });        
        }

        if ( ! is_null($attendanceId) ) {
            $taskItems->whereHas('task.job.attendance', function($query) use ($attendanceId) {
                $query->where('id', '=', $attendanceId);
            });         
        }

        if ( ! is_null($jobTypeId) ) {
            $taskItems->whereHas('task.job.job_type', function($query) use ($jobTypeId) {
                $query->where('id', '=', $jobTypeId);
            });         
        }

        if ( ! is_null($jobActivityId) ) {
            $taskItems->whereHas('task.job.job_activity', function($query) use ($jobActivityId) {
                $query->where('id', '=', $jobActivityId);
            });         
        }

        if ( ! is_null($creationId) ) {
            $taskItems->where('task.responsible_id', '=', $creationId);      
        }

        if ( ! is_null($responsibleId) ) {
            $taskItems->where('task.responsible_id', '=', $responsibleId);      
        }

        if( ! is_null($status) ) {
            $taskItems->whereHas('task.job', function($query) use ($status) {
                $query->where('status_id', '=', $status);
            });
        }

        $taskItems->orderBy('task_item.date', 'ASC');

        if ($paginate) {
            $paginate = $taskItems->paginate(50);
            $result = $paginate->items();
            $page = $paginate->currentPage();
            $total = $paginate->total();
        } else {
            $result = $taskItems->get();
            $total = $taskItems->count();
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

    public static function getMyTask($id)
    {
        $task = Task::findOrFail($id);

        if($task->responsible->user->id != User::logged()->id) return;

        $task->responsible;
        $task->job;
        $task->items;
        $task->job_activity;
        return $task;
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
                $task->task;
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
                $task->task;
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

        if($task == null) {
            return;
        }

        $oldTask = clone $task;
        $childs = Task::where('task_id', '=', $task->id)->get();
        
        foreach($childs as $child) {
            Task::remove($child->id);
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
        $task->budget()->delete();

        foreach($task->project_files as $projectFile) {
            ProjectFile::remove($projectFile->id);
        }

        foreach($task->specification_files as $specificationFile) {
            SpecificationFile::remove($specificationFile->id);
        }

        $task->delete();
        Task::modifyReopened($oldTask);
    }

    public static function removeMyTask($id)
    {
        $task = Task::find($id);

        if($task == null) {
            return;
        }

        $oldTask = clone $task;
        $childs = Task::where('task_id', '=', $task->id)->get();
        
        foreach($childs as $child) {
            Task::remove($child->id);
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
        $task->budget()->delete();

        foreach($task->project_files as $projectFile) {
            ProjectFile::remove($projectFile->id);
        }

        foreach($task->specification_files as $specificationFile) {
            SpecificationFile::remove($specificationFile->id);
        }

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
        return $this->belongsTo('App\Employee', 'responsible_id')->withTrashed();
    }

    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id')->with('job_activity');
    }

    public function budget()
    {
        return $this->hasOne('App\Budget', 'task_id');
    }

    public function project_files()
    {
        return $this->hasMany('App\ProjectFile', 'task_id');
    }

    public function specification_files()
    {
        return $this->hasMany('App\SpecificationFile', 'task_id');
    }

    public function setDurationAttribute($value)
    {
        $this->attributes['duration'] = (float)str_replace(',', '.', $value);
    }
}
