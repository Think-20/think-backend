<?php

namespace App;

use Illuminate\Database\Eloquent\Collection;

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
}