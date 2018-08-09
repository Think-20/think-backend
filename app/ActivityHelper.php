<?php

namespace App;

use DateTime;
use DateInterval;
use Illuminate\Database\Eloquent\Collection;

class ActivityHelper
{

    public static function swapActivities(Task $task1, Task $task2)
    {
        if ($task1->duration == $task2->duration) {
            #$tempR = $task1->responsible_id;
            $tempA = $task1->available_date;
            $tempD = $task1->duration;
            $tempItems = $task1->items;
            
            #$task1->responsible_id = $task2->responsible_id;
            $task1->available_date = $task2->available_date;
            $task1->duration = $task2->duration;
            $task1->save();

            foreach ($task2->items as $item) {
                $item->task_id = $task1->id;
                $item->save();
            }
            
            #$task2->responsible_id = $tempR;
            $task2->available_date = $tempA;
            $task2->duration = $tempD;
            $task2->save();

            foreach ($tempItems as $item) {
                $item->task_id = $task2->id;
                $item->save();
            }
        } else {
            throw new \Exception('A duração das tarefas estão diferentes.');
        }
    }

    public static function moveActivity(array $task1, array $task2)
    {
        $task = isset($task1['id']) ? Task::find($task1['id']) : Task::find($task2['id']);
        $nextDate = isset($task1['id']) ? $task2['items'][0]['date'] : $task1['items'][0]['date'];

        if (DateHelper::calculateIntervalInDays(new DateTime('now'), new DateTime($nextDate)) < 0) {
            throw new \Exception('Você não pode trocar por uma data menor que a de hoje.');
        }

        $arr = ActivityHelper::calculateNextDate($nextDate, $task->job_activity, $task->type()->getResponsibleList(), $task->duration);

        /* Teste com calculadora de datas */
        if (!DateHelper::compare($arr['date'], new DateTime($nextDate))) {
            throw new \Exception('Há um conflito entre datas e não podemos trocar.');
        }

        $task->items()->delete();
        $task->available_date = $nextDate;
        $task->responsible_id = $arr['responsible']->id;
        $task->save();
        $task->saveItems();

        /*
        if($task->duration <= 1) {
            //Verificar qual é o responsável que está livre
            $task->available_date = $nextDate;
            $task->save();
            $item->items[0];
            $item->date = $nextDate;
            $item->save();
            return;
        //Mais de 1 dia de trabalho, verificar se a agenda está disponível para aquele ou outro responsável
        }
         */

    }

    public static function calculateNextDate($initialDate, JobActivity $jobActivity, Collection $professionalList, $duration)
    {
        if (ActivityHelper::checkIfProfessionalListIsEmpty($professionalList)) {
            return [
                'date' => (new DateTime('now'))->format('Y-m-d'),
                'available_responsibles' => []
            ];
        }
        $date = DateHelper::nextUtilIfNotUtil(DateHelper::subUtil(new DateTime($initialDate), 1));
        $professionalId = -1;
        $professionalIn = [];
        $availableProfessionals = [];

        foreach ($professionalList as $professional) {
            $professionalIn[] .= $professional->id;
        }

        do {
            $date = DateHelper::sumUtil($date, 1);
            $items = TaskItem::select()
                ->leftJoin('task', 'task.id', '=', 'task_item.task_id')
                ->where('date', '=', $date->format('Y-m-d'))
                ->whereIn('responsible_id', $professionalIn)
            #->where('job_activity_id', '=', $jobActivity->id)
                ->get();

            $groupedItems = ActivityHelper::groupItemsByProfessional($items, $professionalList);
            $professionalIdsInThisDate = ActivityHelper::getAvailableProfessionalInThisDate($groupedItems);
            $availableProfessionals = ActivityHelper::verifyProfessionalWithDuration($groupedItems, $date, $jobActivity, $professionalIdsInThisDate, $duration);
        } while (count($availableProfessionals) == 0);
        
        return [
            'date' => $date->format('Y-m-d'),
            'available_responsibles' => Employee::whereIn('id', $availableProfessionals)->get()
        ];
    }

    public static function checkIfProfessionalListIsEmpty($professionalList)
    {
        if ($professionalList->count() != 0) {
            return false;
        }
        return true;
    }

    protected static function verifyProfessionalWithDuration(array $groupedItems, DateTime $date, JobActivity $jobActivity, array $professionalIds, $duration)
    {
        $availableProfessionals = [];

        foreach ($professionalIds as $professionalId) {
            $dates = [];
            $incDate = DateHelper::subUtil($date, 1);

            for ($i = 0; $i < ceil($duration); $i++) {
                $incDate = DateHelper::sumUtil($incDate, 1);
                $dates[] = $incDate->format('Y-m-d');
            }

            $items = TaskItem::select()
                ->leftJoin('task', 'task.id', '=', 'task_item.task_id')
                ->whereIn('date', $dates)
                ->where('responsible_id', '=', $professionalId)
                ->get();

            if($items->count() == 0) {
                $availableProfessionals[] = $professionalId;
            }
        }

        return $availableProfessionals;
    }

    protected static function getAvailableProfessionalInThisDate(array $groupedItems)
    {
        $ids = [];

        foreach ($groupedItems as $key => $value) {
            if ($value < 1) {
                $ids[] = $key;
            }
        }

        return $ids;
    }

    protected static function groupItemsByProfessional(Collection $items, Collection $professionalList)
    {
        $arr = [];

        foreach ($professionalList as $professional) {
            $arr[$professional->id] = 0;
        }

        foreach ($items as $item) {
            $arr[$item->responsible_id] = $arr[$item->responsible_id] + $item->duration;
        }

        return $arr;
    }
}