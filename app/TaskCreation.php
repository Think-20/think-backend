<?php

namespace App;

class TaskCreation implements TaskInterface {
    public function getResponsibleList() {
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