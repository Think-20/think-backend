<?php

namespace App;

use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use stdClass;

class TaskHelper
{
    public static function getDates(DateTime $initialDate, DateTime $finalDate, JobActivity $jobActivity, array $onlyEmployees, \Illuminate\Support\Collection $excludeItemIds = null) 
    {
        $items = TaskHelper::checkNextDates($initialDate, $finalDate, $jobActivity, new Collection($excludeItemIds));
        $itemsWithSpecificEmployees = TaskHelper::filterByEmployees($items, $onlyEmployees);

        return $itemsWithSpecificEmployees;
    }

    public static function filterByEmployees(Collection $items, array $onlyEmployees): Collection {
        if(count($onlyEmployees) == 0) return $items;

        return $items->filter(function($item) use ($onlyEmployees) {
            return in_array($item->responsible_id, $onlyEmployees);
        });
    }

    public static function getNextAvailableDate(DateTime $initialDate, JobActivity $jobActivity, Employee $onlyResponsible = null, \Illuminate\Support\Collection $excludeItemIds = null) 
    {
        do {
            $finalDate = DateHelper::sumUtil($initialDate, 1);
            $items = TaskHelper::checkNextDates($initialDate, $finalDate, $jobActivity, $excludeItemIds);
            $initialDate = DateHelper::sumUtil($initialDate, 1);
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

        while($initialDate->format('Y-m-d') <= $finalDate->format('Y-m-d')) {
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

    public static function checkNextDates(DateTime $initialDate, DateTime $finalDate, JobActivity $jobActivity, \Illuminate\Support\Collection $excludeItemIds) : Collection {
        $items = new Collection();
        $itemsForVerification = TaskHelper::getItemsForVerification($initialDate, $finalDate, $jobActivity, $excludeItemIds)
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

    public static function getItemsForVerification(DateTime $initialDate, DateTime $finalDate, JobActivity $jobActivity, \Illuminate\Support\Collection $excludeItemIds): \Illuminate\Support\Collection {
        $itemsForVerification = TaskItem::selectRaw('date, responsible_id, duration,
        budget_value, user.id as user_id, job_activity_id')
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
        ->whereNotIn('task_item.id', $excludeItemIds);

        $jobActivityIdsToCompound = [ $jobActivity->id ];
        $idsBudgetShared = $jobActivity->share_budget->map(function($budgetShared) {
            return $budgetShared->to_id;
        })->toArray();
        $idsDurationShared = $jobActivity->share_duration->map(function($durationShared) {
            return $durationShared->to_id;
        })->toArray();
        
        if(count($idsBudgetShared) > 0) {
            $jobActivityIdsToCompound = array_merge($jobActivityIdsToCompound, $idsBudgetShared);
        }

        if(count($idsDurationShared) > 0) {
            $jobActivityIdsToCompound = array_merge($jobActivityIdsToCompound, $idsDurationShared);
        }

        $itemsForVerification->whereIn('job_activity_id', $jobActivityIdsToCompound);
        
        /* Garantir que o próprio ID buscado existe para agrupar na soma */
        $idsBudgetShared[] = $jobActivity->id;
        $idsDurationShared[] = $jobActivity->id;

        return TaskHelper::group($itemsForVerification->get()->toBase(), $idsBudgetShared, $idsDurationShared);
    }

    public static function group(\Illuminate\Support\Collection $collection, array $idsBudgetShared, $idsDurationShared) {
        if($collection->count() === 0) 
            return $collection;

        $newCollection = new Collection();

        $date = $collection[0]->date;
        $responsible_id = $collection[0]->responsible_id;
        $duration = 0;
        $budget_value = 0;
        $finalKey = $collection->count() - 1;

        foreach($collection as $key => $item) {
            $duration += in_array($item->job_activity_id, $idsDurationShared) ? $item->duration : 0;
            $budget_value += in_array($item->job_activity_id, $idsBudgetShared) ? $item->budget_value : 0;

            if( ($responsible_id != $item->responsible_id)
                || ($date != $item->date)
                || ($key == $finalKey)
                || ($key == 0 && $responsible_id != $collection[$key + 1]->responsible_id)
                || ($key == 0 && $date != $collection[$key + 1]->date) ) 
            {
                $item->duration = $duration;
                $item->budget_value = $budget_value;

                $newCollection->push($item);

                $duration = 0;
                $budget_value = 0;
            }
        }
        return $newCollection;
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