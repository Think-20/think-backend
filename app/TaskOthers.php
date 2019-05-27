<?php

namespace App;

use Illuminate\Database\Eloquent\Collection;
use DateTime;

class TaskOthers implements TaskInterface {
    protected $availableResponsibles;

    public function getResponsibleList(): Collection {
        return new Collection();
    }
    
    public function getMaxCapability() {
        return $this->getResponsibleList()->count();
    }
    
    public function responsiblesByReachedLimit(): Collection {
        return new Collection();
    }

    public function reachedLimit(DateTime $date, $budgetValue): bool {
        return true;
    }

    public function generateNewSuggestDate(DateTime $date, $budgetValue): DateTime {
        return new DateTime();
    }
}