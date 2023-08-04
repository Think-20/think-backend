<?php

namespace App;

use Illuminate\Database\Eloquent\Collection;
use DateTime;

class TaskBudget implements TaskInterface {
    protected $availableResponsibles;

    public function getResponsibleList(): Collection {
        return Employee::where('name', 'LIKE', 'Rafaela%')
        ->where('schedule_active', '=', '1')
        ->get();
    }
    
    public function getMaxCapability() {
        return $this->getResponsibleList()->count();
    }
    
    public function responsiblesByReachedLimit(): Collection {
        return Collection::make($this->availableResponsibles);
    }

    public function reachedLimit(DateTime $date, $budgetValue): bool {
        $this->availableResponsibles = new Collection();

        $limitValue = $this->getMaxBudgetValue();
        $jobActivities = JobActivity::where('description', '=', 'Orçamento')
        ->orWhere('description', '=', 'Modificação de orçamento')
        ->orWhere('description', '=', 'Opção de orçamento')
        ->get();
        $responsibles = $this->getResponsibleList();

        $taskItems = TaskItem::with('task', 'task.job', 'task.responsible', 'task.job_activity')
        ->where('task_item.date', '=', $date->format('Y-m-d'))
        ->whereHas('task', function ($query) use ($jobActivities, $responsibles) {
            $query->whereIn('task.job_activity_id', $jobActivities->pluck('id')->all());
            $query->whereIn('task.responsible_id', $responsibles->pluck('id')->all());
        })
        ->get();

        if($taskItems->count() == 0) {
            $this->availableResponsibles = $this->getResponsibleList();
        } else if($taskItems->count() == 5) {
            return true;
        }

        $grouped = $taskItems->groupBy('task.responsible_id');

        foreach($grouped as $key => $items) {    
            $itemsSum = 0;
    
            foreach($items as $item) {
                $itemsSum = $itemsSum + $item->budget_value;
            }
    
            if($itemsSum == $limitValue) {
                return true;
            }

            $this->availableResponsibles->add($items[0]->task->responsible);
        }

        return false;
    }

    public function quantityAvailable(DateTime $date, Employee $responsible): float {
        $limitValue = $this->getMaxBudgetValue();
        $jobActivities = JobActivity::where('description', '=', 'Orçamento')
        ->orWhere('description', '=', 'Modificação de orçamento')
        ->orWhere('description', '=', 'Opção de orçamento')
        ->get();

        $taskItems = TaskItem::with('task', 'task.job')
        ->where('task_item.date', '=', $date->format('Y-m-d'))
        ->whereHas('task', function ($query) use ($jobActivities, $responsible) {
            $query->whereIn('task.job_activity_id', $jobActivities->pluck('id')->all());
            $query->where('task.responsible_id', $responsible->id);
        })
        ->get();
 
        $itemsSum = 0.0;

        foreach($taskItems as $item) {   
            $itemsSum = $itemsSum + $item->budget_value;
        }

        $quantity = $limitValue - $itemsSum;

        return $quantity >= 0 ? $quantity : 0;
    }

    public function getMaxBudgetValue(): float {
        return 400000.0;
    }

    public function generateNewSuggestDate(DateTime $date, $budgetValue): DateTime {
        $date = DateHelper::sumUtil(new DateTime(), 1);

        while( $this->reachedLimit($date, 0) ) {
            $date = DateHelper::sumUtil($date, 1);
        }

        return $date;
    }
}