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
        'date', 'task_id', 'duration', 'budget_value', 'force'
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

        if ($itemTask2Id != null) {
            $itemTask2->date = $itemTask1->date;
            $itemTask2->save();
        }

        $itemTask1->date = $targetDate;
        $itemTask1->save();
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
            'task.items',
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
            'task.task.job_activity'
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
            $taskItems->whereHas('task', function ($query) use ($jobActivityArrayId) {
                $query->whereIn('task.job_activity_id', $jobActivityArrayId);
            });
        }

        if (!is_null($responsibleArrayId)) {
            $taskItems->whereHas('task', function ($query) use ($responsibleArrayId) {
                $query->whereIn('task.responsible_id', $responsibleArrayId);
            });
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
            $taskItems->whereHas('task', function ($query) use ($creationId) {
                $query->where('responsible_id', $creationId);
            });
        }

        if (!is_null($responsibleId)) {
            $taskItems->whereHas('task', function ($query) use ($responsibleId) {
                $query->where('responsible_id', $responsibleId);
            });
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

    public static function filterMyItems($params)
    {
        $user = User::logged();

        $iniDate = isset($params['iniDate']) ? $params['iniDate'] : null;
        $finDate = isset($params['finDate']) ? $params['finDate'] : null;
        $paginate = isset($params['paginate']) ? $params['paginate'] : true;

        $tasks = Task::select('task.*')->with(['items' => function ($query) {
            $query->first();
        }])
            ->leftJoin('job', 'job.id', '=', 'task.job_id')
            ->where(function ($query) use ($user) {
                $query->where('job.attendance_id', '=', $user->employee->id);
                $query->orWhere('task.responsible_id', '=', $user->employee->id);
            });

        if (!is_null($iniDate) && !is_null($finDate)) {
            $sql = '(task_item.date >= "' . $iniDate . '"';
            $sql .= ' AND task_item.date <= "' . $finDate . '")';
            $tasks->whereRaw($sql);
        }

        $tasks->orderBy('task_item.date', 'ASC');

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
