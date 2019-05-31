<?php

namespace App;

class TaskMemorial implements TaskInterface {
    public function getResponsibleList() {
        return null;
    }
    
    public function getMaxCapability() {
        return $this->getResponsibleList()->count();
    }

    public function getMaxBudgetValue(): float {
        return 0.0;
    }
}