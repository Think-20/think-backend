<?php

namespace App;

use Illuminate\Database\Eloquent\Collection;

class TaskBudget implements TaskInterface {
    public function getResponsibleList(): Collection {
        return Employee::where('name', 'LIKE', 'Rafaela%')
        ->where('schedule_active', '=', '1')
        ->get();
    }
    
    public function getMaxCapability() {
        return $this->getResponsibleList()->count();
    }
}