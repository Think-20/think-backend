<?php

namespace App;
use DateTime;
use DateInterval;
use Illuminate\Database\Eloquent\Collection;

class ActivityHelper {

    public static function calculateNextDate($initialDate, Collection $professionalList, $duration, Collection $modelList, $dateField = 'available_date') {
        $initialDate = DateHelper::validDate(new DateTime($initialDate));
        ActivityHelper::checkIfProfessionalListIsEmpty($professionalList);

        $professionalIdList = $professionalList->map(function($model) use ($dateField) {
            return $model->id;
        });

        if($modelList->count() == 0) {
            return ['date' => $initialDate, 'responsible' => $professionalList->find($professionalIdList[0])];
        }

        $professionalDateList = ActivityHelper::searchDate($modelList, $professionalIdList, $initialDate, $dateField, $duration);
        $professionalsArrayWithDates = ActivityHelper::organizeData($professionalIdList, $professionalDateList);
        $professionalSelected = array_shift($professionalsArrayWithDates);
        
        return [
            'date' => $professionalSelected['date'],
            'responsible' => $professionalList->find($professionalSelected['id'])
        ];
    }

    protected static function organizeData($professionalIdList, $professionalDateList) {
        $professionalsArrayWithDates = $professionalIdList->toArray();
        $thisDate = DateHelper::nextUtilIfNotUtil(new DateTime('now'));

        foreach($professionalsArrayWithDates as $key => $id) {
            $professionalsArrayWithDates[$key] = [
                'id' => $id,
                'date' => isset($professionalDateList[$key]) ? $professionalDateList[$key] : $thisDate
            ];
        }

        uasort($professionalsArrayWithDates, function($a, $b) use (&$arr) {
            if ($a['date']->format('Y-m-d') == $b['date']->format('Y-m-d')) {
                return 0;
            }
            else if($a['date']->format('Y-m-d') < $b['date']->format('Y-m-d')) {
                return -1;
            } else {
                return 1;
            }
        });

        return $professionalsArrayWithDates;
    }
    
    protected static function searchDate($modelList, $professionalIdList, $initialDate, $dateField, $duration, $lastExecutionDateList = null) {
        $professionalDateList = [];
        $professionalDateWrite = [];


        foreach($modelList as $key => $model) {
            $date = new DateTime($model->{$dateField});

            $estimatedTime = $model->estimated_time != null ? $model->estimated_time : 1;
            $estimatedTimeOfCreation = (int) ceil($estimatedTime);
            $availableDateOfCreation = DateHelper::sumUtil($date, $estimatedTimeOfCreation);

            $responsible_id = $model->responsible_id;
            $indexProfessionalDateList = array_search($responsible_id, $professionalIdList->toArray());

            if( ! isset($professionalDateWrite[$indexProfessionalDateList])) {
                $professionalDateWrite[$indexProfessionalDateList] = true;
            }

            $projectInThisDate = $modelList->filter(function($model) use ($availableDateOfCreation, $dateField, $responsible_id) {
              return DateHelper::compare(new DateTime($model->{$dateField}), $availableDateOfCreation)
                && $model->responsible_id == $responsible_id;
            });

            $nextProjectDates = $modelList->filter(function($model) use ($availableDateOfCreation, $dateField, $responsible_id) {
              return (new DateTime($model->{$dateField}))->format('Y-m-d') > $availableDateOfCreation->format('Y-m-d')
                && $model->responsible_id == $responsible_id;
            });

            $newDate = null;
            $intervalBetweenProjects = 0;

            if($nextProjectDates->count() > 0) {
                $newDate = (new DateTime($nextProjectDates->values()->get(0)->{$dateField}));
                
                if($availableDateOfCreation->format('Y-m-d') <= $initialDate->format('Y-m-d')
                && $initialDate->format('Y-m-d') <= $newDate->format('Y-m-d')
                && $availableDateOfCreation < $initialDate ) {
                    $availableDateOfCreation = $modelList->filter(function($model) use ($initialDate, $dateField, $responsible_id) {
                       return DateHelper::compare(new DateTime($model->{$dateField}), $initialDate)
                       && $responsible_id == $model->responsible_id;    
                    })->count() > 0 
                        ? $availableDateOfCreation
                        : $initialDate;
                }

                $intervalBetweenProjects = DateHelper::calculateIntervalUtilInDays($availableDateOfCreation, $newDate);                
            }

            if($intervalBetweenProjects >= $duration 
            && $availableDateOfCreation >= $initialDate
            && $availableDateOfCreation->format('Y-m-d') <= $initialDate->format('Y-m-d')
            && $initialDate->format('Y-m-d') <= $newDate->format('Y-m-d')) {
                $professionalDateList[$indexProfessionalDateList] = $availableDateOfCreation;
                $professionalDateWrite[$indexProfessionalDateList] = false;
            }

            if($projectInThisDate->count() == 0 
            && DateHelper::compare($initialDate, $availableDateOfCreation) 
            && ActivityHelper::enoughTime($modelList, $availableDateOfCreation, $dateField, $duration)) {
                $professionalDateList[$indexProfessionalDateList] = $availableDateOfCreation;
                $professionalDateWrite[$indexProfessionalDateList] = false;
            } else if($initialDate < $availableDateOfCreation && $professionalDateWrite[$indexProfessionalDateList]) {
                $professionalDateList[$indexProfessionalDateList] = $availableDateOfCreation;
            }
        }

        return $professionalDateList;
    }

    public static function enoughTime($modelList, $availableDateOfCreation, $dateField, $duration) {
        $ini = $availableDateOfCreation->format('Y-m-d');
        $fin = DateHelper::sumUtil($availableDateOfCreation, $duration)->format('Y-m-d');
        return $modelList->filter(function($model) use ($ini, $fin, $dateField) {
            $date = (new DateTime($model->{$dateField}))->format('Y-m-d');
            return $ini < $date && $date < $fin ? $model : null;
        })->count() === 0;
    }

    public static function checkIfProfessionalListIsEmpty($professionalList) {
        if($professionalList->count() != 0) {       
            return;
        }
        throw new \Exception('A lista de profissionais está vazia.');
    }

}