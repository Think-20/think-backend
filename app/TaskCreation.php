<?php

namespace App;

class TaskCreation implements TaskInterface {
    public function getResponsibleList() {
        return Employee::whereHas('department', function($query) {
            $query->where('description', '=', 'Criação');
        })->get();
    }
    
    public function getMaxCapability() {
        return $this->getResponsibleList()->count();
    }
}