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
        $jobActivity = JobActivity::where('description', '=', 'Orçamento')->first();
        $responsibles = TaskBudget::getResponsibleList();

        $taskItems = TaskItem::with('task', 'task.job', 'task.responsible')
        ->where('task_item.date', '=', $date->format('Y-m-d'))
        ->whereHas('task', function ($query) use ($jobActivity, $responsibles) {
            $query->where('task.job_activity_id', '=', $jobActivity->id);
            $query->whereIn('task.responsible_id', $responsibles->pluck('id')->all());
        })
        ->get();

        if($taskItems->count() == 0) {
            $this->availableResponsibles = $this->getResponsibleList();
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
        $jobActivity = JobActivity::where('description', '=', 'Orçamento')->first();

        $taskItems = TaskItem::with('task', 'task.job')
        ->where('task_item.date', '=', $date->format('Y-m-d'))
        ->whereHas('task', function ($query) use ($jobActivity, $responsible) {
            $query->where('task.job_activity_id', '=', $jobActivity->id);
            $query->where('task.responsible_id', $responsible->id);
        })
        ->get();
 
        $itemsSum = 0.0;

        foreach($taskItems as $item) {   
            $itemsSum = $itemsSum + $item->budget_value;
        }

        return $limitValue - $itemsSum;
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