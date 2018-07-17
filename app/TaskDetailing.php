<?php

namespace App;

class TaskDetailing implements TaskInterface {
    public function getResponsibleList() {
        return Employee::where('name', 'LIKE', 'Willyane%')->get();
    }
    
    public function getMaxCapability() {
        return $this->getResponsibleList()->count();
    }
}