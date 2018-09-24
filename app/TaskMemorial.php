<?php

namespace App;

class TaskMemorial implements TaskInterface {
    public function getResponsibleList(Job $job) {
        return Employee::where('department_id', '=', $job->attendance_id)
        ->where('schedule_active', '=', '1')
        ->get();
    }
    
    public function getMaxCapability() {
        return $this->getResponsibleList()->count();
    }
}