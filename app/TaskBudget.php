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

        $limitValue = 400000;
        $jobActivity = JobActivity::where('description', '=', 'Orçamento')->first();
        $responsibles = TaskBudget::getResponsibleList();

        $tasks = Task::with('job', 'responsible')
        ->where('available_date', '=', $date->format('Y-m-d'))
        ->where('job_activity_id', '=', $jobActivity->id)
        ->whereIn('responsible_id', $responsibles->pluck('id')->all())
        ->get();

        if($tasks->count() == 0) {
            $this->availableResponsibles = $this->getResponsibleList();
        }

        $grouped = $tasks->groupBy('responsible_id');

        foreach($grouped as $key => $tasks) {
            if($tasks->count() >= 5) {
                return true;
            }
    
            $jobs = new Collection();
    
            foreach($tasks as $task) {
                $jobs->add($task->job);
            }
    
            if($tasks->count() > 0 && ($jobs->sum('budget_value') + $budgetValue) > $limitValue) {
                return true;
            }

            $this->availableResponsibles->add($tasks[0]->responsible);
        }

        return false;
    }

    public function generateNewSuggestDate(DateTime $date, $budgetValue): DateTime {
        $date = DateHelper::sumUtil(new DateTime(), 1);

        while( $this->reachedLimit($date, 0) ) {
            $date = DateHelper::sumUtil($date, 1);
        }

        return $date;
    }
}