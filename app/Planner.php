<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Planner extends Model
{
    protected $table = 'planner';

    protected $guarded = ['id'];

    public static function list()
    {
        $planners = Planner::get();

        foreach ($planners as $planner) {            
            $planner->employee;
            $planner->modality;
        }

        return $planners;
    }

    public static function getUnique(int $id = null)
    {
        $planner = Planner::find($id);

        if (!$planner) {
            return null;
        }

        $planner->employee;
        $planner->modality;

        return $planner;
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, "id", "employee_id");
    }

    public function modality()
    {
        return $this->hasOne(TimecardPlace::class, "id", "modality_id");
    }

}
