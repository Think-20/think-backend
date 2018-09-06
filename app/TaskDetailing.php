<?php

namespace App;

class TaskDetailing implements TaskInterface {
    public function getResponsibleList() {
        return Employee::where('name', 'LIKE', 'Willyane%')
        ->where('schedule_active', '=', '1')
        ->get();
    }
    
    public function getMaxCapability() {
        return $this->getResponsibleList()->count();
    }
}