<?php

namespace App;

use Illuminate\Database\Eloquent\Collection;
use DateTime;

class TaskCreation implements TaskInterface {
    public function getResponsibleList(): Collection {
        return Employee::whereHas('department', function($query) {
            $query->where('description', '=', 'Criação');
        })
        ->where('schedule_active', '=', '1')
        ->get();
    }
    
    public function getMaxCapability() {
        return $this->getResponsibleList()->count();
    }

    public function reachedLimit(DateTime $date, $budgetValue): bool {
        return true;
    }

    public function generateNewSuggestDate(DateTime $date, $budgetValue): DateTime {
        return new DateTime();
    }

    public function responsiblesByReachedLimit(): Collection {
        return new Collection;
    }
}