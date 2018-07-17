<?php

namespace App;

class TaskBudget implements TaskInterface {
    public function getResponsibleList() {
        return Employee::where('name', 'LIKE', 'Rafaela%')->get();
    }
    
    public function getMaxCapability() {
        return $this->getResponsibleList()->count();
    }
}