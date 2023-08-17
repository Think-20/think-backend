<?php

namespace App\Http\Controllers;

use App\Job;
use App\Reminder;
use App\User;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RemindersController extends Controller
{
    public function index(Request $request){
        $this->OneYearJobThisWeak();
        $onlyNotRead = $request->only("onlyNotRead") ?? null;
        if($onlyNotRead){
            $reminders = Reminder::where('employee_id', User::logged()->employee->id)->where('read', false)->get(['id', 'message', 'read', 'created_at', 'category']);
        }else{
            $reminders = Reminder::where('employee_id', User::logged()->employee->id)->get(['id', 'message', 'read', 'created_at', 'category']);
        }
        $groupedReminders = [];
        foreach ($reminders as $reminder) {
            $category = $reminder->category;
    
            if (!isset($groupedReminders[$category])) {
                $groupedReminders[$category] = [];
            }
    
            $groupedReminders[$category][] = $reminder;
        }
        return response()->json($groupedReminders);
    }

    public function OneYearJobThisWeak(){
        $jobs = Job::where('attendance_id', User::logged()->employee->id)
        ->where('status_id', 3)
        ->whereDate('status_updated_at', '>=', Carbon::now()->subYear())
        ->whereDate('status_updated_at', '<=', Carbon::now()->subYear()->addDays(7))
        ->with('client')
        ->get();

        $messages = [];
        foreach ($jobs as $job) {
            $message = 'O projeto ';

            if (isset($job->client)) {
                $message .= $job->client['name'];
            } elseif (isset($job->not_client)) {
                $message .= $job->not_client;
            }

            $dateTime = new DateTime($job->status_updated_at);
            $formattedDate = $dateTime->format('d/m/Y');

            $message .= ' do evento ' . $job->event . ' foi APROVADO nesse mesmo período do ano passado (dia ' .$formattedDate. '), contate o cliente.';

            $reminder = Reminder::where('message', $message)->first();
            if(!$reminder){
                $newReminder = new Reminder();
                $newReminder->message = $message;
                $newReminder->read = false;
                $newReminder->metadata = "jobid".$job->id;
                $newReminder->employee_id = User::logged()->employee->id;
                $newReminder->category = "1 ano de aprovação de projeto";
                $newReminder->save();
            }
        }
    }

    public function markAsRead($id){
        $reminder = Reminder::where('employee_id', User::logged()->employee->id)->where('id', $id)->where('read', false)->first();
        if($reminder){
            $reminder->read = true;
            $reminder->save();
            return response()->json(["message" => "Reminder " . $reminder->id . " marked as read"]);
        }else{
            return response()->json(["message" => "Reminder not found"], 404);
        }
    }
}
