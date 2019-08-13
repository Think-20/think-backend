<?php

namespace App;

use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use stdClass;

class TaskHelper
{
    public static function getDates(DateTime $initialDate, DateTime $finalDate, JobActivity $jobActivity, array $onlyEmployees) 
    {
        $items = TaskHelper::checkNextDates($initialDate, $finalDate, $jobActivity);
        $itemsWithSpecificEmployees = TaskHelper::filterByEmployees($items, $onlyEmployees);

        return $itemsWithSpecificEmployees;
    }

    public static function filterByEmployees(Collection $items, array $onlyEmployees): Collection {
        if(count($onlyEmployees) == 0) return $items;

        return $items->filter(function($item) use ($onlyEmployees) {
            return in_array($item->responsible_id, $onlyEmployees);
        });
    }

    public static function getNextAvailableDate(DateTime $initialDate, JobActivity $jobActivity, Employee $onlyResponsible = null) 
    {
        do {
            $finalDate = DateHelper::sumUtil($initialDate, 30);
            $items = TaskHelper::checkNextDates($initialDate, $finalDate, $jobActivity);
            $initialDate = DateHelper::sumUtil($initialDate, 30);
            $availableDates = $items->filter(function($item) use ($onlyResponsible) {
                if($onlyResponsible != null) {
                    return $item->status == 'true' && $item->responsible_id == $onlyResponsible->id;
                }
                return $item->status == 'true';
            });
        } while($availableDates->count() == 0);

        return $availableDates->first();
    }

    public static function completeDates(DateTime $initialDate, DateTime $finalDate, Collection $employees): Collection {
        $items = new Collection();
        $initialDate = DateHelper::sumUtil(DateHelper::sub($initialDate, 1), 1);

        while($initialDate->format('Y-m-d') < $finalDate->format('Y-m-d')) {
            foreach($employees as $employee) {
                $std = new stdClass();
                $std->duration = 0;
                $std->budget_value = 0;
                $std->date = $initialDate->format('Y-m-d');
                $std->responsible_id = $employee->id;
                $std->user_id = $employee->user->id;
                $items->push($std);
            }

            $initialDate = DateHelper::sumUtil($initialDate, 1);
        }

        return $items;
    }

    public static function checkNextDates(DateTime $initialDate, DateTime $finalDate, JobActivity $jobActivity) : Collection {
        $items = new Collection();
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
        $unionItems = TaskHelper::merge($completeDates->toBase(), $itemsForVerification->toBase());

        foreach($unionItems as $item) {
            try {
                TaskHelper::checkIfDateAvailable($item, $jobActivity);
                $item->status = 'true';
                $item->message = '';
            } catch(Exception $e) {
                $item->status = 'false';
                $item->message = $e->getMessage();
            }        
            $items->push($item);
        }

        return $items;
    }

    public static function checkIfDateAvailable($item, JobActivity $jobActivity): void {
        TaskHelper::checkDuration($item, $jobActivity);
        TaskHelper::checkBudgetValue($item, $jobActivity);
        TaskHelper::checkBlocked($item);
        TaskHelper::checkOnlyNextDay($item, $jobActivity);
        TaskHelper::checkPeriod($item, $jobActivity);
        TaskHelper::checkOldDate($item, $jobActivity);
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

    public static function checkOnlyNextDay($item, JobActivity $jobActivity) {
        if($jobActivity->next_day == 0 
            || $item->date != (new DateTime('now'))->format('Y-m-d')
        ) return;

        throw new Exception('Não é permitido agendar ' . mb_strtolower($jobActivity->description) . 
        ' no mesmo dia');
    }

    public static function checkPeriod($item, JobActivity $jobActivity) {
        $today = new DateTime('now');
        $hourMinute = (int) $today->format('Hi');
        
        if($jobActivity->next_period == 0) return;
        if($hourMinute < 1200 || $item->date != $today->format('Y-m-d')) return;

        throw new Exception('Não é permitido agendar ' . mb_strtolower($jobActivity->description) . 
        ' no mesmo período');
    }

    public static function checkOldDate($item, JobActivity $jobActivity) {
        if($item->date >= (new DateTime('now'))->format('Y-m-d')) return;

        throw new Exception('Não é permitido agendar em datas anteriores');
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

    public static function merge(\Illuminate\Support\Collection $completeDates, \Illuminate\Support\Collection $itemsForVerification) {
        $dates = new Collection();
        
        foreach($completeDates as $completeDate) {
            $filteredItems = $itemsForVerification->filter(function($item) use ($completeDate) {
                return $completeDate->date == $item->date 
                    && $completeDate->responsible_id == $item->responsible_id;
            });

            if($filteredItems->count() > 0) {
                $dates->push($filteredItems->first());
            } else {
                $dates->push($completeDate);                
            }
        }

        return $dates;
    }
}