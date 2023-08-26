<?php

namespace App\Http\Controllers;

use App\Client;
use App\Job;
use App\JobActivity;
use App\Reminder;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RemindersController extends Controller
{
    public function index(Request $request)
    {
        $jobs = $this->OneYearJobCreation();
        $clients = $this->OneYearClientRegister();
        $approveds = $this->OneYearJobApproved();
        $return = [
            $jobs,
            $clients,
            $approveds
        ];

        return $return;
    }

    public function OneYearJobCreation()
    {
        $startDate = Carbon::now()->subYear()->startOfDay();
        $endDate = Carbon::now()->subYear()->endOfDay();
        $jobs = Job::selectRaw('job.*')
            ->with(
                'job_activity',
                'job_type',
                'client',
                'main_expectation',
                'levels',
                'how_come',
                'agency',
                'attendance',
                'competition',
                'files',
                'status',
                'creation'
            )
            ->with(['creation.items' => function ($query) {
                $query->limit(1);
            }])
            ->where('attendance_id', User::logged()->employee->id)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->with('client')
            ->get();

        return ["jobs" => $jobs];
    }

    public function markAsRead($id)
    {
        $reminder = Reminder::where('employee_id', User::logged()->employee->id)->where('id', $id)->where('read', false)->first();
        if ($reminder) {
            $reminder->read = true;
            $reminder->save();
            return response()->json(["message" => "Reminder " . $reminder->id . " marked as read"]);
        } else {
            return response()->json(["message" => "Reminder not found"], 404);
        }
    }

    public function OneYearClientRegister()
    {
        $startDate = Carbon::now()->subYear()->startOfDay();
        $endDate = Carbon::now()->subYear()->endOfDay();
        $clients = Client::where('employee_id', User::logged()->employee->id)
            ->with('type', 'status')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->get();
        if (!$clients->isEmpty()) {
            foreach ($clients as $client) {
                $lastJob = Job::where('client_id', $client->id)->orderBy('created_at', 'desc')->first(['code', 'event', 'created_at']);
                if ($lastJob) {
                    $id = str_pad((string)$lastJob->code, 4, "0", STR_PAD_LEFT) . "/" . $lastJob->created_at->year;;
                    $client->lastJobId = $id;
                    $client->lastJobEvent = $lastJob->event;
                }
            }
        }


        return ["clients" => $clients];
    }

    public function OneYearJobApproved()
    {
        $startDate = Carbon::now()->subYear()->startOfDay();
        $endDate = Carbon::now()->subYear()->endOfDay();
        $jobs = Job::selectRaw('job.*')
        ->with(
            'job_activity',
            'job_type',
            'client',
            'main_expectation',
            'levels',
            'how_come',
            'agency',
            'attendance',
            'competition',
            'files',
            'status',
            'creation',
            'tasks'
        )
        ->with(['creation.items' => function ($query) {
            $query->limit(1);
        }])
        ->where(function ($query) {
            $query->where('attendance_id', User::logged()->employee->id)
                ->orWhereHas('tasks', function ($query) {
                    $query->where('responsible_id', User::logged()->employee->id)
                        ->where('job_activity_id', JobActivity::where('description', 'Projeto')->first()->id);
                });
        })
        ->where('status_id', 3)
        ->whereDate('status_updated_at', '>=', $startDate)
        ->whereDate('status_updated_at', '<=', $endDate)
        ->with('client')
        ->get();

        return ["jobs_approveds" => $jobs];
    }
}
