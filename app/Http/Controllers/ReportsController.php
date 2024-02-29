<?php

namespace App\Http\Controllers;

use App\Employee;
use App\Job;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Services\ReportsService;

class ReportsController extends Controller
{
    private $reportsService;
    public function __construct(ReportsService $reportsService)
    {
        $this->reportsService = $reportsService;
    }

    public function read(Request $request)
    {



        $data = $request->only([
            'date_init',
            'date_end',
            'name',
            'status',
            'creation',
            'attendance',
            'job_type',
            'job_activity',
            'status',
            'jobs_amount',
            'event'
        ]);

        $loggedDepartament = Employee::where('id', User::logged()->employee_id)->first();

        $jobsPerPage = $data['jobs_amount'] ?? 30;
        $currentPage = $request->query('page', 1);

        $dtInit = Carbon::parse($data["date_init"]);
        $dtEnd = Carbon::parse($data["date_end"]);
        $monthDif = ($dtEnd->diffInMonths($dtInit) == 0) ? 1 : $dtEnd->diffInMonths($dtInit)+1;

        if ($loggedDepartament->department_id == 1) {
            //Caso o usuario seja dos departamentos acima, quer dizer que pode ver todos os dados de relatório
            $jobs = $this->reportsService->baseQuery($data)->orderBy('created_at', 'asc')
                ->paginate($jobsPerPage);
        } else {
            //Caso o usuário não seja dos departamentos do IF, quer dizer que ele só pode ver dos jobs em que faz parte.
            $jobs = $this->reportsService->baseQuery($data)->where('attendance_id', $loggedDepartament->id)->orderBy('created_at', 'asc')->paginate($jobsPerPage);
        }


        if ($jobs->isEmpty()) {
            return response()->json(["error" => false, "message" => "Jobs not found"]);
        }


        foreach ($jobs as &$job) {
            foreach ($job->tasks as $task) {
                if (isset($data['creation']) && in_array('external', $data['creation'])) {
                    unset($task->responsible);
                    $task->setAttribute("responsible", ["name" => "Externo"]);
                } else {
                    if ($task->job_activity->description == 'Projeto' || $task->job_activity->description == 'Outsider') {
                        $job->setAttribute('creation_responsible', $task->responsible);
                        if ($task->updated_at != $task->created_at) {
                            $job->setAttribute('project_conclusion', $task->updated_at->toArray()['formatted']);
                        }
                    }
                    if (isset($task->final_value) && $task->final_value != null) {
                        $job->setAttribute('lastValue', $task->final_value);
                    }
                }
            }
            if ($job["attendance_comission_id"] != null) {
                if (!isset($data['attendance']) || count($data['attendance']) <= 0) {
                    $job->setAttribute('specialAttendance', $job->attendance->name . '/' . $job->attendance_comission->name);
                    $job->setAttribute('specialBudget', $job->budget_value);
                    $job->setAttribute('specialFinalValue', $job->final_value);
                } else {
                    if (!in_array($job["attendance_comission_id"], $data['attendance']) && in_array($job->attendance->id, $data['attendance'])) {
                        $percentage = (100 - $job->comission_percentage) / 100;
                        $job->setAttribute('specialAttendance', $job->attendance->name);
                        $job->setAttribute('specialBudget', $job->budget_value * $percentage);
                        $job->setAttribute('specialFinalValue', $job->final_value * $percentage);
                    } elseif (in_array($job["attendance_comission_id"], $data['attendance']) && !in_array($job->attendance->id, $data['attendance'])) {
                        $percentage = $job->comission_percentage / 100;
                        $job->setAttribute('specialAttendance', $job->attendance_comission->name);
                        $job->setAttribute('specialBudget', $job->budget_value * $percentage);
                        $job->setAttribute('specialFinalValue', $job->final_value * $percentage);
                    } elseif (in_array($job["attendance_comission_id"], $data['attendance']) && in_array($job->attendance->id, $data['attendance'])) {
                        $job->setAttribute('specialAttendance', $job->attendance->name . '/' . $job->attendance_comission->name);
                        $job->setAttribute('specialBudget', $job->budget_value);
                        $job->setAttribute('specialFinalValue', $job->final_value);
                    }
                }
            }
        }


        $adjustedIndex = ($currentPage - 1) * $jobsPerPage;
        $jobs->transform(function ($job) use (&$adjustedIndex) {
            $adjustedIndex++;
            $job->setAttribute('index', $adjustedIndex);
            return $job;
        });

        $total_value = $this->reportsService->sumBudgetValue($data);
        $standby = $this->reportsService->sumStandby($data);
        $types = $this->reportsService->getTypes($data);
        $averageTimeToAproval = $this->reportsService->sumTimeToAproval($data);
        $aprovalsAmount = $this->reportsService->sumAprovals($data);
        $approvedJobs = $this->reportsService->averageApprovedJobsPerMonth($data);
        $advancedJobs = $this->reportsService->averageAdvancedJobsPerMonth($data);
        $average_ticket = $this->reportsService->averageTicket($data);

        if ($total_value['sum'] > 0) {
            $conversionRate = round(($aprovalsAmount['sum'] / $total_value['sum']) * 100) . "%";
        } else {
            $conversionRate = 0;
        }

        $anualTendenceAprovation = $approvedJobs['valueNumber'] * 12;

        $jobs_month_average  = $jobs->total() / $monthDif;
        $total_month_average  = $total_value['sum'] / $monthDif;

        dd([
            $monthDif,
            $jobs->total(),
            $total_value,
            $jobs_month_average,
            $total_month_average
        ]);

        return response()->json([
            "jobs" => $jobs,
            "total_value" => $total_value['sum'],
            "average_ticket" => $average_ticket,
            "averate_time_to_aproval" => $averageTimeToAproval,
            "aprovals_amount" => $aprovalsAmount,
            "conversion_rate" => [$conversionRate, $aprovalsAmount['sum']],
            "anualTendenceAprovation" => $anualTendenceAprovation,
            "anualTendenceAprovationCount" => $approvedJobs['amount'],
            "standby_projects" => ["amount" => $standby['count'], "value" => $standby['sum']],
            "types" => $types,
            "averageApprovedJobsPerMonth" => $approvedJobs,
            "averageAdvancedJobs" => $advancedJobs,
            'updatedInfo' => Job::updatedInfo(),
            'total_month_average' => $total_month_average,
            'jobs_month_average' => $jobs_month_average
        ]);
    }

    public static function creation_responsibles($tasks)
    {
        foreach ($tasks as $task) {
            $responsibles = [];
            if ($task->job_activity->description == 'Projeto' || $task->job_activity->description == 'Outsider') {
                array_push($responsibles, $task->responsible);
            }
            return $responsibles;
        }
    }

    public function reprocess()
    {
        $tasks = Task::where('final_value', '<>', 'null')->orderBy('updated_at', 'desc')->get();
        if ($tasks) {
            foreach ($tasks as $task) {
                $job = Job::where('id', $task->job_id)->where('final_value', null)->first();
                if ($job) {
                    $job->final_value = $task->final_value;
                    $job->save();
                }
            }
        }
        return response()->json("ok");
    }
}
