<?php

namespace App;

use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use stdClass;

class TaskHelper
{
    public static function getAvailableDates(DateTime $initialDate, DateTime $finalDate, JobActivity $jobActivity) 
    {
        return TaskHelper::checkNextAvailableDates($initialDate, $finalDate, $jobActivity);
    }

    public static function getNextAvailableDate(DateTime $initialDate, DateTime $finalDate, JobActivity $jobActivity) 
    {
        $datesAvailable = TaskHelper::checkNextAvailableDates($initialDate, $finalDate, $jobActivity->responsibles);
        return $datesAvailable->first();
    }

    public static function completeDates(DateTime $initialDate, DateTime $finalDate, Collection $employees): Collection {
        $availableDates = new Collection();
        $initialDate = DateHelper::sumUtil(DateHelper::sub($initialDate, 1), 1);

        while($initialDate->format('Y-m-d') < $finalDate->format('Y-m-d')) {
            foreach($employees as $employee) {
                $std = new stdClass();
                $std->duration = 0;
                $std->budget_value = 0;
                $std->date = $initialDate->format('Y-m-d');
                $std->responsible_id = $employee->id;
                $std->user_id = $employee->user->id;
                $availableDates->push($std);
            }

            $initialDate = DateHelper::sumUtil($initialDate, 1);
        }

        return $availableDates;
    }

    public static function checkNextAvailableDates(DateTime $initialDate, DateTime $finalDate, JobActivity $jobActivity): Collection {
        $availableDates = new Collection();
        $itemsForVerification = TaskHelper::getItemsForVerification($initialDate, $finalDate, $jobActivity)
        ->map(function($item) {
            $std = new stdClass();
            $std->duration = $item->duration;
            $std->budget_value = $item->budget_value;
            $std->date = $item->date;
            $std->responsible_id = $item->responsible_id;
            $std->user_id = $item->user_id;
            return $std;
        });
        $completeDates = TaskHelper::completeDates($initialDate, $finalDate, $jobActivity->responsibles);
        $unionItems = $itemsForVerification->union($completeDates);

        foreach($unionItems as $item) {
            try {
                TaskHelper::checkIfDateAvailable($item, $jobActivity);
                $item->status = 'true';
                $item->message = '';
            } catch(Exception $e) {
                $item->status = 'false';
                $item->message = $e->getMessage();
            }        
            $availableDates->push($item);
        }

        return $availableDates;
    }

    public static function checkIfDateAvailable($item, JobActivity $jobActivity): void {
        TaskHelper::checkDuration($item, $jobActivity);
        TaskHelper::checkBudgetValue($item, $jobActivity);
        TaskHelper::checkBlocked($item);
    }

    public static function checkDuration($item, JobActivity $jobActivity) {
        if($item->duration < 1 || $jobActivity->max_duration_value_per_day == 0) 
            return;

        throw new Exception('A agenda da data ' . (new DateTime($item->date))->format('d/m/Y') . 
        ' está em uso para o responsável');
    }

    public static function checkBudgetValue($item, JobActivity $jobActivity) {
        if($jobActivity->max_budget_value_per_day == 0 || $item->budget_value < $jobActivity->max_budget_value_per_day) 
            return;

        throw new Exception('O orçamento da data ' . (new DateTime($item->date))->format('d/m/Y') . 
        ' está completo para o responsável');
    }

    public static function checkBlocked($item) {
        if(!ScheduleBlock::checkIfBlocked($item->date, $item->user_id)) 
            return;

        throw new Exception('A data ' . (new DateTime($item->date))->format('d/m/Y') . 
        ' está bloqueada para o responsável');
    }

    public static function getItemsForVerification(DateTime $initialDate, DateTime $finalDate, JobActivity $jobActivity): Collection {
        return TaskItem::selectRaw('date, responsible_id, SUM(task_item.duration) as duration,
        SUM(task_item.budget_value) as budget_value, user.id as user_id')
        ->join('task', 'task.id', 'task_item.task_id')
        ->join('employee', 'employee.id', 'task.responsible_id')
        ->join('user', 'user.employee_id', 'employee.id')
        ->where('task_item.date', '>=', $initialDate->format('Y-m-d'))
        ->where('task_item.date', '<=', $finalDate->format('Y-m-d'))
        ->whereIn('task.responsible_id', $jobActivity->responsibles->map(
            function(Employee $employee) 
            { 
                return $employee->id; 
            }
        ))
        ->groupBy('task_item.date', 'task.responsible_id', 'user.id')
        ->get();
    }
}