<?php

namespace App;

use Illuminate\Database\Eloquent\Collection;
use DateTime;

class TaskBudget implements TaskInterface {
    public function getResponsibleList(): Collection {
        return Employee::where('name', 'LIKE', 'Rafaela%')
        ->where('schedule_active', '=', '1')
        ->get();
    }
    
    public function getMaxCapability() {
        return $this->getResponsibleList()->count();
    }

    public function reachedLimit(DateTime $date): bool {
        $limitValue = 400000;
        $jobActivity = JobActivity::where('description', '=', 'Orçamento')->first();

        $tasks = Task::with('job')
        ->where('available_date', '=', $date->format('Y-m-d'))
        ->where('job_activity_id', '=', $jobActivity->id)
        ->get();

        if($tasks->count() >= 5) {
            return true;
        }

        $jobs = new Collection();

        foreach($tasks as $task) {
            $jobs->add($task->job);
        }

        if($tasks->count() > 0 && $jobs->sum('budget_value') >= $limitValue) {
            return true;
        }

        return false;
    }

    public function generateNewSuggestDate(): DateTime {
        $date = DateHelper::sumUtil(new DateTime(), 1);

        while( $this->reachedLimit($date) ) {
            $date = DateHelper::sumUtil($date, 1);
        }

        return $date;
    }
}