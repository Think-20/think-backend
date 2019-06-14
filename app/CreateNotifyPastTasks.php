<?php

namespace App;

use DateTime;

class CreateNotifyPastTasks {
    public function __constructor() {
        $jobActivities = JobActivity::where('description', '=', 'Memorial descritivo')
        ->orWhere('description', '=', 'Projeto')
        ->orWhere('description', '=', 'Outsider')
        ->orWhere('description', '=', 'Modificação')
        ->orWhere('description', '=', 'Continuação')
        ->orWhere('description', '=', 'Opção')
        ->get();

        $yesterday = DateHelper::sub(new DateTime(), 1)->format('Y-m-d');

        $tasks = Task::where('done', '=', '0')
        ->whereRaw('DATE_ADD(available_date, INTERVAL duration DAY) = "' . $yesterday . '"')
        ->whereIn('job_activity_id', $jobActivities->map(function($jobActivity) {
            return $jobActivity->id;
        }))->get();
        
        foreach($tasks as $task) {
            $message = 'A tarefa ' . $task->getTaskName() . ' da ';
            $message .= $task->job->getJobName();
            $message .= ' está atrasada.';

            Notification::createAndNotify(Agent::automatic(), [
                'message' => $message
            ], NotificationSpecial::createMulti([
                'user_id' => $task->responsible->user->id,
                'message' => $message
            ]), 'Atraso de tarefa', $task->id, true);
        }
    }
}