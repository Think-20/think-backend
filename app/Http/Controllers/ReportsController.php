<?php

namespace App\Http\Controllers;

use App\Job;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public static function read(Request $request)
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

        $jobsPerPage = $data['jobs_amount'] ?? 30;
        $currentPage = $request->query('page', 1);

        $jobs = self::baseQuery($data)->orderBy('created_at', 'asc')->paginate($jobsPerPage);
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

        $total_value = self::sumBudgetValue($data);
        $standby = self::sumStandby($data);
        $types = self::getTypes($data);
        $averageTimeToAproval = self::sumTimeToAproval($data);
        $aprovalsAmount = self::sumAprovals($data);
        $approvedJobs = self::averageApprovedJobsPerMonth($data);
        $advancedJobs = self::averageAdvancedJobsPerMonth($data);


        if ($total_value['sum'] > 0) {
            $conversionRate = ceil(($aprovalsAmount['sum'] / $total_value['sum']) * 100) . "%";
        } else {
            $conversionRate = 0;
        }

        if ($total_value['count'] > 0) {
            $average_ticket = $total_value['sum'] / $total_value['count'];
        } else {
            $average_ticket = 0;
        }

        $anualTendenceAprovation = $approvedJobs['valueNumber'] * 12;

        return response()->json([
            "jobs" => $jobs,
            "total_value" => number_format($total_value['sum'], 2, ',', '.'),
            "average_ticket" => number_format($average_ticket, 2, ',', '.'),
            "averate_time_to_aproval" => $averageTimeToAproval,
            "aprovals_amount" => $aprovalsAmount,
            "conversion_rate" => [$conversionRate,  number_format($aprovalsAmount['sum'], 2, ',', '.')],
            "anualTendenceAprovation" => number_format($anualTendenceAprovation, 2, ',', '.'),
            "standby_projects" => ["amount" => $standby['count'], "value" => $standby['sum']],
            "types" => $types,
            "averageApprovedJobsPerMonth" => $approvedJobs,
            "averageAdvancedJobs" => $advancedJobs,
            'updatedInfo' => Job::updatedInfo()
        ]);
    }

    private static function baseQuery($data)
    {
        $name = $data['name'] ?? null;
        $creationId = isset($data['creation']) ? $data['creation'] : null;
        $attendanceId = isset($data['attendance']) ? $data['attendance'] : null;
        $jobTypeId = isset($data['job_type']) ? $data['job_type'] : null;
        $status = isset($data['status']) ? $data['status'] : null;
        $event = isset($data['event']) ? $data['event'] : null;
        $jobActivity = isset($data['job_activity']) ? $data['job_activity'] : null;
        $jobs = Job::selectRaw('job.*')
            ->with(
                'job_activity',
                'job_type',
                'client',
                'agency',
                'attendance',
                'status',
                'creation',
                'tasks',
                'attendance_comission'
            )
            ->with(['creation.items' => function ($query) {
                $query->limit(1);
            }]);

        if ((User::logged()->employee->department->description != "Diretoria" && User::logged()->employee->department->description != "Planejamento")) {
            $jobs->where('attendance_id', User::logged()->employee->id)->orWhere('attendance_comission_id', User::logged()->employee->id);
        }

        if ($name) {
            $jobs->where(function ($query) use ($name) {
                $query->whereHas('client', function ($subquery) use ($name) {
                    $subquery->where('fantasy_name', 'LIKE', '%' . $name . '%');
                    $subquery->orWhere('name', 'LIKE', '%' . $name . '%');
                });
                $query->orWhere('not_client', 'LIKE', '%' . $name . '%');
            });
        }

        if ($jobTypeId) {
            $jobs->whereIn('job_type_id', $jobTypeId);
        }

        if ($creationId && in_array('external', $creationId)) {
            $jobs->whereHas('job_activity', function ($query) {
                $query->where('description', 'like', '%externo%');
            });
        }

        if ($jobActivity) {
            $jobs->whereHas('job_activity', function ($query) use ($jobActivity) {
                $query->whereIn('id', $jobActivity);
            });
        }

        if ($event) {
            $jobs->where('event', 'LIKE', '%' . $event . '%');
        }

        if ($status) {
            $jobs->whereIn('status_id', $status);
        }

        if ($creationId && !in_array('external', $creationId)) {
            $jobs->whereHas('creation', function ($query) use ($creationId) {
                $query->whereIn('responsible_id', $creationId);
            });
        }

        if ($attendanceId) {
            $jobs->whereHas('attendance', function ($query) use ($attendanceId) {
                $query->whereIn('id', $attendanceId);
            });
            $jobs->orWHereIn('attendance_comission_id', $attendanceId);
        }

        if (isset($data['date_init'])) {
            $jobs->where('created_at', '>=', Carbon::parse($data['date_init'])->format('Y-m-d'));
        } else {
            $jobs->where('created_at', '>=', Carbon::now()->startOfYear()->format('Y-m-d'));
        }

        if (isset($data['date_end'])) {
            $jobs->where('created_at', '<=', Carbon::parse($data['date_end'])->format('Y-m-d'));
        } else {
            $jobs->where('created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'));
        }
        return $jobs;
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

    public static function sumBudgetValue($data)
    {
        $jobs = self::baseQuery($data);

        $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.final_value) as sum'))->first();

        return ["sum" => $result->sum != null ? $result->sum : 0, "count" => $result->count > 0 ? $result->count : 0];
    }

    public static function sumTimeToAproval($data)
    {
        $jobs = self::baseQuery($data);

        $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.time_to_aproval) as sumTimeToAproval'))->first();

        $count = $result->count > 0 ? $result->count : 0;
        $sumTimeToAproval = $result->sumTimeToAproval != null ? $result->sumTimeToAproval : 0;

        if ($count > 0) {
            return ceil($sumTimeToAproval / $count);
        } else {
            return 0;
        }
    }

    public static function sumAprovals($data)
    {
        $jobs = self::baseQuery($data);

        $result = $jobs->select(DB::raw('COUNT(*) as count, SUM(job.final_value) as sum'))->where('status_id', 3)->first();

        return ["sum" => $result->sum != null ? $result->sum : 0, "count" => $result->count > 0 ? $result->count : 0];
    }

    public static function sumStandby($data)
    {
        $jobs = self::baseQuery($data);

        $result = $jobs
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(job.final_value) as sum')
            )
            ->where('status_id', 1)
            ->first();

        if (!$result) {
            return ["sum" => number_format(0, 2, ',', '.'), "count" => 0];
        }
        return ["sum" => number_format($result->sum, 2, ',', '.'), "count" => $result->count];
    }

    public static function getTypes($data)
    {
        $jobs = self::baseQuery($data);

        $countStand = clone $jobs;
        $countStand = $countStand->whereHas('job_type', function ($query) {
            $query->where('description', 'Stand');
        })->count();

        $countShowroom = clone $jobs;
        $countShowroom = $countShowroom->whereHas('job_type', function ($query) {
            $query->where('description', 'Showroom');
        })->count();

        $countCenografia = clone $jobs;
        $countCenografia = $countCenografia->whereHas('job_type', function ($query) {
            $query->where('description', 'Cenografia');
        })->count();

        $countPdv = clone $jobs;
        $countPdv = $countPdv->whereHas('job_type', function ($query) {
            $query->where('description', 'Pdv');
        })->count();

        $countOutsider = clone $jobs;
        $countOutsider = $countOutsider->whereHas('job_type', function ($query) {
            $query->where('description', 'Outsider');
        })->count();

        // Retornar as contagens em um array associativo
        $counts = [
            'stand' => $countStand,
            'showroom' => $countShowroom,
            'cenografia' => $countCenografia,
            'pdv' => $countPdv,
            'outsider' => $countOutsider,
        ];
        return $counts;
    }

    public static function averageApprovedJobsPerMonth($data)
    {
        // Calcula a diferença de meses
        $monthsPassed = self::monthDiff($data);
        $baseQuery = self::baseQuery($data);

        $jobs = $baseQuery->select(DB::raw('COUNT(*) as count, MONTH(created_at) as month, SUM(final_value) as final_value'))
            ->where('status_id', 3)
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->get();

        if ($jobs->isEmpty()) {
            return ["amount" => 0, "value" => 0, "valueNumber" => 0];
        }

        // Somar a quantidade de jobs aprovados por mês
        $totalJobsApproved = 0;
        $totalValueJobsApproved = 0;
        foreach ($jobs as $job) {
            $totalJobsApproved += $job->count;
            $totalValueJobsApproved += $job->final_value;
        }

        // Calcular a média de jobs aprovados por mês
        $averageJobsPerMonth = ceil($totalJobsApproved / $monthsPassed);
        $totalValueJobsApprovedNumber = $totalValueJobsApproved / $monthsPassed;
        $totalValueJobsApproved = number_format(($totalValueJobsApproved / $monthsPassed), 2, ',', '.');

        return ["amount" => $averageJobsPerMonth, "value" => $totalValueJobsApproved, "valueNumber" => $totalValueJobsApprovedNumber];
    }

    public static function averageAdvancedJobsPerMonth($data)
    {
        $jobs = self::baseQuery($data);

        $result = $jobs
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(job.final_value) as sum')
            )
            ->where('status_id', 5)
            ->first();

        if (!$result) {
            return ["sum" => number_format(0, 2, ',', '.'), "count" => 0];
        }
        return ["sum" => number_format($result->sum, 2, ',', '.'), "count" => $result->count];
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

    public static function monthDiff($data)
    {
        // Converte as datas para objetos Carbon
        $inicio = isset($data["date_init"]) ? Carbon::parse($data["date_init"]) : Carbon::now()->startOfYear();
        $fim = isset($data['date_end']) ? Carbon::parse($data['date_end']) : Carbon::now()->endOfMonth();


        // Calcula a diferença de meses
        $monthsPassed = $fim->diffInMonths($inicio);

        // Se as datas estão no mesmo mês, a diferença é 1
        if ($monthsPassed === 0) {
            $monthsPassed = 1;
        } else {
            // Adiciona mais um pq a diferença de meses nunca conta o mês inicial
            $monthsPassed = $monthsPassed + 1;
        }

        return $monthsPassed;
    }
}
