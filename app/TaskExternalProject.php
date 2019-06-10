<?php

namespace App;

use Illuminate\Database\Eloquent\Collection;
use DateTime;

class TaskExternalProject implements TaskInterface {
    public function getResponsibleList(): Collection {
        return Employee::where('name', 'LIKE', 'Rafaela%')
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

    public function getMaxBudgetValue(): float {
        return 0.0;
    }
}