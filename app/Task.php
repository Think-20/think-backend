<?php

namespace App;

use DateTime;
use Exception;
use stdClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;


class Task extends Model
{
    protected $table = 'task';
    protected $fillable = [
        'job_id', 'responsible_id', 'job_activity_id',
        'reopened', 'task_id', 'done'
    ];

    public function getTaskName()
    {
        $pad = $this->reopened > 0 ? str_pad($this->reopened, 2, '0', \STR_PAD_LEFT) : '';
        return trim($this->job_activity->description . ' ' . $pad);
    }

    public static function responsiblesByActivity($jobActivityId)
    {
        $jobActivity = JobActivity::find($jobActivityId);
        return $jobActivity->responsibles;
    }

    public static function getNextAvailableDate(string $availableDate, string $jobActivity)
    {
        $initialDate = new DateTime($availableDate);
        $jobActivity = JobActivity::where('description', '=', $jobActivity)->first();

        return TaskHelper::getNextAvailableDate($initialDate, $jobActivity);
    }

    public static function getNextAvailableDates(array $data)
    {
        $jobActivityId = isset($data['job_activity']['id']) ? $data['job_activity']['id'] : null;
        $onlyEmployees = isset($data['only_employee']['id']) ? [$data['only_employee']['id']] : [];

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
        $itemTask1 = isset($data['taskItem1']) ? $data['taskItem1'] : null;
        $itemTask2 = isset($data['taskItem2']) ? $data['taskItem2'] : null;
        $onlyItem = isset($data['onlyItem']) ? $data['onlyItem'] : null;

        if (isset($itemTask1['id']) && isset($itemTask2['id']) 
            && isset($itemTask1['task']['id']) && isset($itemTask2['task']['id'])) {
            if ($onlyItem) {
                TaskItem::swapItems($itemTask1['id'], $itemTask2['id']);
                return;
            }
            Task::swapTasks($itemTask1['task']['id'], $itemTask2['task']['id']);
            return;
        }

        if ($itemTask1['id'] == null) {
            $temp = $itemTask2;
            $itemTask2 = $itemTask1;
            $itemTask1 = $temp;
        }

        if ($itemTask1['id'] == null) {
            throw new \Exception('Os dados fornecidos não são válidos para operações.');
        }

        if ($onlyItem) {
            TaskItem::swapItems($itemTask1['id'], null, $itemTask2['date']);
            return;
        }
        Task::swapTasks($itemTask1['task']['id'], null, $itemTask2['date']);
        return;

        //TO DO: implementar notificações

        /*

        if (($task1->job->attendance_id != $task2->job->attendance_id) || $task1->job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não pode alterar uma tarefa que não pertence a você.');
        }

        if($onlyItem) {
            
        }

        dd($itemTask1, $itemTask2);
        

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

        */
        return true;
    }

    public static function swapTasks(int $task1Id, int $task2Id = null, $targetDate = null)
    {
        if ($task1Id == $task2Id)
            throw new Exception('Os itens não podem pertencer a mesma tarefa!');

        $task1 = Task::find($task1Id);
        $items1 = $task1->items;
        $duration1 = (float) $task1->items->sum('duration');
        $task1->items()->delete();
        $ids = $items1->pluck('id')->toArray();

        if($task2Id != null) {
            $task2 = Task::find($task2Id);
            $items2 = $task2->items;
            $duration2 = (float) $task2->items->sum('duration');
            $task2->items()->delete();
            $ids = array_merge($ids, $items2->pluck('id')->toArray());
            $targetDate = $items2->first()->date;
        }

        $dates1 = TaskHelper::getDates(
            new DateTime($items1->first()->date),
            new DateTime($items1->first()->date),
            $task1->job_activity,
            [],
            collect($ids)
        );

        $datesAvailable1 = $dates1->filter(function ($item) {
            return $item->status == 'true';
        });

        $item = $dates1->first();

        if ($datesAvailable1->count() == 0)
            throw new Exception('Não será possível começar a partir dessa data, operação abortada, ' . mb_strtolower($item->message));

        $initialDate = new DateTime($targetDate);
        $newItems1 = new Collection();
        $responsibleId = 0;
        while ($duration1 > 0) {
            $nextDate = TaskHelper::getNextAvailableDate(
                $initialDate,
                $task1->job_activity,
                $responsibleId != 0 ? Employee::find($responsibleId) : null,
                collect($ids)
            );

            if ($responsibleId == 0)
                $responsibleId = $nextDate->responsible_id;

            $duration1 = $duration1 - TaskItem::durationSub((float) $nextDate->duration);
            $initialDate = DateHelper::sumUtil($initialDate, 1);
            $newItems1->push($nextDate);
        }
        $task1->responsible_id = $responsibleId;
        $task1->save();
        $task1->saveItems($newItems1);


        if($task2Id != null) {
            $dates2 = TaskHelper::getDates(
                new DateTime($items2->first()->date),
                new DateTime($items2->first()->date),
                $task2->job_activity,
                [],
                collect($ids)
            );

            $datesAvailable2 = $dates2->filter(function ($item) {
                return $item->status == 'true';
            });

            $item2 = $dates2->first();

            if ($datesAvailable2->count() == 0)
                throw new Exception('Não será possível começar a partir dessa data, operação abortada, ' . mb_strtolower($item2->message));

            $initialDate = new DateTime($items1->first()->date);
            $newItems2 = new Collection();
            $responsibleId = 0;
            while ($duration2 > 0) {
                $nextDate = TaskHelper::getNextAvailableDate(
                    $initialDate,
                    $task2->job_activity,
                    $responsibleId != 0 ? Employee::find($responsibleId) : null,
                    collect($ids),
                    true
                );

                if ($responsibleId == 0)
                    $responsibleId = $nextDate->responsible_id;

                $duration2 = $duration2 - TaskItem::durationSub((float) $nextDate->duration);
                $initialDate = DateHelper::sumUtil($initialDate, 1);
                $newItems2->push($nextDate);
            }
            $task2->responsible_id = $responsibleId;
            $task2->save();
            $task2->saveItems($newItems2);
        }
    }

    public static function myEditAvailableDate(array $data)
    {
        //TO DO: implementar troca de itens para atendimento
        $task = null;

        if (isset($data['task1']['id']) && isset($data['task2']['id'])) {
            $oTask1 = (object) $data['task1'];
            $oTask2 = (object) $data['task2'];
            $task1 = Task::find($oTask1->id);
            $task2 = Task::find($oTask2->id);

            if (($task1->job->attendance_id != $task2->job->attendance_id) || $task1->job->attendance_id != User::logged()->employee->id) {
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
        } else {
            $task = ActivityHelper::moveActivity($data['task1'], $data['task2']);

            if ($task->job->attendance_id != User::logged()->employee->id) {
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

    public function insertAutomatic(JobActivity $jobActivity, NotifierInterface $notifier = null, Employee $onlyResponsible = null)
    {
        $date = new DateTime('now');
        $items = new Collection();
        $items->push(TaskHelper::getNextAvailableDate($date, $jobActivity, $onlyResponsible));
        $responsible = Employee::find($items->first()->responsible_id);

        $data = [
            'responsible' => ['id' => $responsible->id],
            'job' => ['id' => $this->job->id],
            'job_activity' => ['id' => $jobActivity->id],
            'items' => $items->toArray(),
            'task' => [
                'id' => $this->id,
            ]
        ];

        $notifier = $notifier != null ? $notifier : $responsible;
        Task::insert($data, $notifier);
    }

    public static function insert(array $data, NotifierInterface $notifier = null)
    {
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        $job_id = isset($data['job']['id']) ? $data['job']['id'] : null;
        $job_activity_id = isset($data['job_activity']['id']) ? $data['job_activity']['id'] : null;
        $task_id = isset($data['task']['id']) ? $data['task']['id'] : null;
        $items = isset($data['items']) ? collect($data['items'])->map(function ($item) {
            return (object) $item;
        }) : collect([]);

        $jobActivity = JobActivity::find($job_activity_id);
        $task = new Task(array_merge($data, [
            'responsible_id' => $responsible_id,
            'job_id' => $job_id,
            'job_activity_id' => $job_activity_id,
            'task_id' => $task_id,
        ]));

        $task->saveItems($items);

        if ($task->initialDate() != null) {
            $message = $task->getTaskName() . ' da ';
            $message .= $task->job->getJobName();
            $message .= ' agendado em ' . (new DateTime($task->initialDate()))->format('d/m/Y') . ' para ' . $task->responsible->name;

            if ($notifier == null) {
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

    public function initialDate()
    {
        return $this->items->count() == 0 ? null : $this->items->first()->date;
    }

    public static function modifyReopened(Task $task)
    {
        if ($task->job_activity->counter == 0) {
            return;
        }

        $sum = 1;

        foreach ($task->job->tasks as $t) {
            if ($t->job_activity->description == $task->job_activity->description) {
                $t->reopened = $sum;
                $t->save();
                $sum++;
            }
        }
    }

    public function fixItemsByFixedDuration(Collection $items, JobActivity $jobActivity): Collection
    {
        if ($jobActivity->fixed_duration === 0)
            return $items;

        foreach ($items as $item) {
            $item->duration = $jobActivity->fixed_duration;
        }

        return $items;
    }

    public function fixItemsByMaxDurationValuePerDay(Collection $items, JobActivity $jobActivity): Collection
    {
        if (
            $jobActivity->max_duration_value_per_day === 0
            || $jobActivity->fixed_duration != 0
        )
            return $items;

        foreach ($items as $item) {
            /* Usando o máximo permitido, se uso for 0, usar 1. Se uso for 0.3, usar 0.7 */
            $item->duration = (1 - $item->duration);
        }

        return $items;
    }

    public function appendItemsByBudget(Collection $items, JobActivity $jobActivity): Collection
    {
        $fixedBudgetValue = (float) $jobActivity->fixed_budget_value;

        if ($fixedBudgetValue == 0)
            return $items;

        $idsBudgetShared = $jobActivity->share_budget->map(function ($budgetShared) {
            return $budgetShared->to_id;
        })->toArray();
        $idsBudgetShared[] = $jobActivity->id;
        $maxValuePerDay = JobActivity::whereIn('id', $idsBudgetShared)
            ->get()
            ->sum('max_budget_value_per_day');

        $nextItem = $items->pop();
        $responsible = Employee::find($nextItem->responsible_id);
        //100%, 30% do valor, conforme o parâmetro fixed_budget_value
        $jobValue = (float) $this->job->budget_value * $jobActivity->fixed_budget_value;

        do {
            $usedInThisDate = (float) $nextItem->budget_value;

            if ($usedInThisDate < $maxValuePerDay) {
                $availableInThisDate = $maxValuePerDay - $usedInThisDate;
                $available = $jobValue > $availableInThisDate ? $availableInThisDate : $jobValue;
                $nextItem->budget_value = $available;
                $jobValue = $jobValue <= $available ? 0 : $jobValue - $available;
                $items->push($nextItem);
            }

            $date = DateHelper::sumUtil(new DateTime($nextItem->date), 1);
            $nextItem = TaskHelper::getNextAvailableDate($date, $jobActivity, $responsible);
        } while ($jobValue > 0);

        return $items;
    }

    public function saveItems(Collection $items)
    {
        $jobActivity = $this->job_activity;
        $items = $this->appendItemsByBudget($items, $jobActivity);
        $items = $this->fixItemsByFixedDuration($items, $jobActivity);
        $items = $this->fixItemsByMaxDurationValuePerDay($items, $jobActivity);

        $this->save();
        TaskItem::insertAll($this, $items->toArray());
    }

    public function deleteItems()
    {
        $this->items()->delete();
    }

    public static function edit(array $data)
    {
        $id = $data['id'];
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
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
        if ($task->job_activity->description == 'Orçamento') {
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


        if ($oldResponsibleId != $task->responsible_id) {
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

        if ($oldDuration != $task->duration) {
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

        if ($oldDate != $task->initialDate()) {
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

        $statusArrayId = isset($params['status_array']) && !empty($params['status_array']) ? array_map(function ($v) {
            return $v['id'];
        }, $params['status_array']) : null;
        $attendanceArrayId = isset($params['attendance_array']) && !empty($params['attendance_array']) ? array_map(function ($v) {
            return $v['id'];
        }, $params['attendance_array']) : null;
        $jobTypeArrayId = isset($params['job_type_array']) && !empty($params['job_type_array']) ? array_map(function ($v) {
            return $v['id'];
        }, $params['job_type_array']) : null;
        $jobActivityArrayId = isset($params['job_activity_array']) && !empty($params['job_activity_array']) ? array_map(function ($v) {
            return $v['id'];
        }, $params['job_activity_array']) : null;
        $responsibleArrayId = isset($params['responsible_array']) && !empty($params['responsible_array']) ? array_map(function ($v) {
            return $v['id'];
        }, $params['responsible_array']) : null;
        $departmentArrayId = isset($params['department_array']) && !empty($params['department_array']) ? array_map(function ($v) {
            return $v['id'];
        }, $params['department_array']) : null;

        $taskItems = TaskItem::with(
            'task',
            'task.responsible',
            'task.job_activity',
            'task.job',
            'task.job.client',
            'task.job.job_type',
            'task.job.status',
            'task.job.agency',
            'task.job.attendance',
            'task.job.job_activity',
            'task.task',
            'task.task.job_activity',
            'task.items'
        );

        if (!is_null($iniDate) && !is_null($finDate)) {
            $taskItems->where('task_item.date', '>=', $iniDate);
            $taskItems->where('task_item.date', '<=', $finDate);
        }

        if (!is_null($attendanceArrayId)) {
            $taskItems->whereHas('task.job.attendance', function ($query) use ($attendanceArrayId) {
                $query->whereIn('id', $attendanceArrayId);
            });
        }

        if (!is_null($jobTypeArrayId)) {
            $taskItems->whereHas('task.job.job_type', function ($query) use ($jobTypeArrayId) {
                $query->whereIn('id', $jobTypeArrayId);
            });
        }

        if (!is_null($statusArrayId)) {
            $taskItems->whereHas('task.job', function ($query) use ($statusArrayId) {
                $query->whereIn('status_id', $statusArrayId);
            });
        }

        if (!is_null($jobActivityArrayId)) {
            $taskItems->whereIn('task.job_activity_id', $jobActivityArrayId);
        }

        if (!is_null($responsibleArrayId)) {
            $taskItems->whereIn('task.responsible_id', $responsibleArrayId);
        }

        if (!is_null($departmentArrayId)) {
            $taskItems->whereHas('task.responsible', function ($query) use ($departmentArrayId) {
                $query->whereIn('department_id', $departmentArrayId);
            });
        }

        if (!is_null($clientName)) {
            $taskItems->whereHas('task.job.client', function ($query) use ($clientName) {
                $query->where('fantasy_name', 'LIKE', '%' . $clientName . '%');
                $query->orWhere('name', 'LIKE', '%' . $clientName . '%');
            });
            $taskItems->orWhereHas('task.job', function ($query) use ($clientName) {
                $query->where('not_client', 'LIKE', '%' . $clientName . '%');
            });
        }

        if (!is_null($attendanceId)) {
            $taskItems->whereHas('task.job.attendance', function ($query) use ($attendanceId) {
                $query->where('id', '=', $attendanceId);
            });
        }

        if (!is_null($jobTypeId)) {
            $taskItems->whereHas('task.job.job_type', function ($query) use ($jobTypeId) {
                $query->where('id', '=', $jobTypeId);
            });
        }

        if (!is_null($jobActivityId)) {
            $taskItems->whereHas('task.job.job_activity', function ($query) use ($jobActivityId) {
                $query->where('id', '=', $jobActivityId);
            });
        }

        if (!is_null($creationId)) {
            $taskItems->where('task.responsible_id', '=', $creationId);
        }

        if (!is_null($responsibleId)) {
            $taskItems->where('task.responsible_id', '=', $responsibleId);
        }

        if (!is_null($status)) {
            $taskItems->whereHas('task.job', function ($query) use ($status) {
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


    public static function updatedInfo()
    {
        $typeIds = NotificationType::where('description', 'LIKE', '%tarefa%')->get();
        $lastData = Notification::with('user', 'notifier')
            ->whereIn('type_id', $typeIds->map(function ($type) {
                return $type->id;
            }))
            ->orderBy('date', 'desc')
            ->limit(1)
            ->first();

        if ($lastData == null) {
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

        if ($task->responsible->user->id != User::logged()->id) return;

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
            ->where(function ($query) use ($user) {
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

            foreach ($paginate as $task) {
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

            foreach ($result as $task) {
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

        if ($task == null) {
            return;
        }

        $oldTask = clone $task;
        $childs = Task::where('task_id', '=', $task->id)->get();

        foreach ($childs as $child) {
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

        foreach ($task->project_files as $projectFile) {
            ProjectFile::remove($projectFile->id);
        }

        foreach ($task->specification_files as $specificationFile) {
            SpecificationFile::remove($specificationFile->id);
        }

        $task->delete();
        Task::modifyReopened($oldTask);
    }

    public static function removeMyTask($id)
    {
        $task = Task::find($id);

        if ($task == null) {
            return;
        }

        $oldTask = clone $task;
        $childs = Task::where('task_id', '=', $task->id)->get();

        foreach ($childs as $child) {
            Task::remove($child->id);
        }

        if ($task->job->attendance_id != User::logged()->employee->id) {
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

        foreach ($task->project_files as $projectFile) {
            ProjectFile::remove($projectFile->id);
        }

        foreach ($task->specification_files as $specificationFile) {
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

    public function type(): TaskInterface
    {
        return TaskFactory::build($this->job_activity->description);
    }

    public function items()
    {
        return $this->hasMany('App\TaskItem', 'task_id')->orderBy('task_item.date', 'ASC');
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
        return $this->belongsTo('App\Task', 'task_id')
            ->with('job_activity', 'job_activity.modify', 'job_activity.option');
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
        $this->attributes['duration'] = (float) str_replace(',', '.', $value);
    }
}
