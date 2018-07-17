<?php

namespace App;
use DateTime;
use DateInterval;
use Illuminate\Database\Eloquent\Collection;

class ActivityHelper {

    public static function calculateNextDate($initialDate, JobActivity $jobActivity, Collection $professionalList, $duration) {
        $date = DateHelper::nextUtilIfNotUtil(DateHelper::subUtil(new DateTime($initialDate), 1));
        $professionalId = -1;
        $professionalIn = '';

        foreach($professionalList as $professional) {
            $professionalIn .= $professional->id . ',';
        }

        $professionalIn = substr($professionalIn, 0, strlen($professionalIn) -1);

        do {
            $date = DateHelper::sumUtil($date, 1);
            $items = TaskItem::select()
            ->leftJoin('task', 'task.id', '=', 'task_item.task_id')
            ->where('date', '=', $date->format('Y-m-d'))
            ->whereIn('responsible_id', $professionalList)
            #->where('job_activity_id', '=', $jobActivity->id)
            ->get();
            
            $groupedItems = ActivityHelper::groupItemsByProfessional($items, $professionalList);
            $professionalIdInThisDate = ActivityHelper::getAvailableProfessionalInThisDate($groupedItems);
            $professionalId = ActivityHelper::verifyProfessionalWithDuration($groupedItems, $date, $jobActivity, $professionalIdInThisDate, $duration);
        } while($professionalId == -1);

        return [
            'date' => $date,
            'responsible' => Employee::find($professionalId)
        ];
    }

    protected static function verifyProfessionalWithDuration(array $groupedItems, DateTime $date, JobActivity $jobActivity, $professionalId, $duration) {
        if($professionalId == -1  
        || $duration == 1 && $groupedItems[$professionalId] == 0 
        || $duration == 0.5 && $groupedItems[$professionalId] == 0.5) {
            return $professionalId;
        }

        $dates = '';

        for($i = 0; $i < ceil($duration); $i++) {
            $incDate = DateHelper::sumUtil($date, 1);
            $dates .= $date->format('Y-m-d') . ',';
        }

        $dates = substr($dates, 0, count($dates) - 1);

        $items = TaskItem::select()
            ->leftJoin('task', 'task.id', '=', 'task_item.task_id')
            ->where('date', 'IN', '(' . $dates . ')')
            ->where('job_activity_id', '=', $jobActivity->id)
            ->where('responsible_id', '=', $professionalId)
            ->get();

        return $items->count() > 0 ? -1 : $professionalId;
    }

    protected static function getAvailableProfessionalInThisDate(array $groupedItems) {
        foreach($groupedItems as $key => $value) {
            if($value < 1) {
                return $key;
            } 
        }

        return -1;
    }

    protected static function groupItemsByProfessional(Collection $items, Collection $professionalList) {
        $arr = [];

        foreach($professionalList as $professional) {
            $arr[$professional->id] = 0;
        }

        foreach($items as $item) {
            $arr[$item->responsible_id] = $arr[$item->responsible_id] + $item->duration;
        }

        return $arr;
    }
}