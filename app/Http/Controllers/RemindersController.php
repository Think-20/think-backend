<?php

namespace App\Http\Controllers;

use App\Client;
use App\Job;
use App\Reminder;
use App\User;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RemindersController extends Controller
{
    public function index(Request $request)
    {
        $jobs = $this->OneYearJobThisWeak();
        $clients = $this->OneYearClientRegister();
        $return = [
            $jobs,
            $clients
        ];

        return $return;
    }

    public function OneYearJobThisWeak()
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
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->with('client')
            ->get();
        if (!$jobs->isEmpty()) {
        }
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
        ->whereDate('created_at', '>=', $startDate)
        ->whereDate('created_at', '<=', $endDate)
            ->get();

        return ["clients" => $clients];
    }
}
