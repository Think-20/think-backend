<?php

namespace App\Http\Service;

use App\Job;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    public static function baseQuery($data)
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

        if ((User::logged()->employee->department->description != "Diretoria" && User::logged()->employee->department->description != "Planejamento")) {
            $jobs->where(function ($query) {
                $query->where('attendance_id', User::logged()->employee->id)
                    ->orWhere('attendance_comission_id', User::logged()->employee->id);
            });
        } else {
            if ($attendanceId) {
                $jobs->whereHas('attendance', function ($query) use ($attendanceId) {
                    $query->whereIn('id', $attendanceId);
                    $query->orWHereIn('attendance_comission_id', $attendanceId);
                });
            }
        }
        return $jobs;
    }

    public static function queryNoUserFilter($data)
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

    public static function sumBudgetValue($data)
    {
        $jobs = self::baseQuery($data);

        if (!isset($data['attendance']) || count($data['attendance']) <= 0) {
            $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.final_value) as sum'))->first();
        } else {
            $result = $jobs->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(
                    CASE
                        WHEN (comission_percentage IS NOT NULL AND comission_percentage > 0) THEN
                            CASE
                                WHEN 
                                    (attendance_comission_id IN (' . implode(',', $data['attendance']) . ') AND 
                                     attendance_id IN (' . implode(',', $data['attendance']) . ')) THEN final_value
                                WHEN attendance_id IN (' . implode(',', $data['attendance']) . ') AND attendance_comission_id NOT IN (' . implode(',', $data['attendance']) . ') THEN final_value * ((100 - comission_percentage) / 100)
                                WHEN attendance_comission_id IN (' . implode(',', $data['attendance']) . ') and attendance_id NOT IN (' . implode(',', $data['attendance']) . ') THEN final_value * (comission_percentage / 100)
                                ELSE final_value
                            END
                        ELSE final_value
                    END
                ) as sum')
            )->first();
        }
        return ["sum" => $result->sum != null ? $result->sum : 0, "count" => $result->count > 0 ? $result->count : 0];
    }

    public static function sumBudgetValueRef($data)
    {
        $jobs = self::queryNoUserFilter($data);

        if (!isset($data['attendance']) || count($data['attendance']) <= 0) {
            $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.final_value) as sum'))->first();
        } else {
            $result = $jobs->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(
                    CASE
                        WHEN (comission_percentage IS NOT NULL AND comission_percentage > 0) THEN
                            CASE
                                WHEN 
                                    (attendance_comission_id IN (' . implode(',', $data['attendance']) . ') AND 
                                     attendance_id IN (' . implode(',', $data['attendance']) . ')) THEN final_value
                                WHEN attendance_id IN (' . implode(',', $data['attendance']) . ') AND attendance_comission_id NOT IN (' . implode(',', $data['attendance']) . ') THEN final_value * ((100 - comission_percentage) / 100)
                                WHEN attendance_comission_id IN (' . implode(',', $data['attendance']) . ') and attendance_id NOT IN (' . implode(',', $data['attendance']) . ') THEN final_value * (comission_percentage / 100)
                                ELSE final_value
                            END
                        ELSE final_value
                    END
                ) as sum')
            )->first();
        }
        return ["sum" => $result->sum != null ? $result->sum : 0, "count" => $result->count > 0 ? $result->count : 0];
    }

    public static function sumTimeToAproval($data)
    {
        $jobs = self::baseQuery($data);
        $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.time_to_aproval) as sum'))->first();
        $count = $result->count > 0 ? $result->count : 0;
        $sum = $result->sum > 0 ? $result->sum : 0;

        return $count > 0 ? round($sum / $count) : 0;
    }

    public static function sumGeneralTimeToAproval($data)
    {
        $jobs = self::queryNoUserFilter($data);
        $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.time_to_aproval) as sum'))->first();
        $count = $result->count > 0 ? $result->count : 0;
        $sum = $result->sum > 0 ? $result->sum : 0;

        return round($sum / $count);
    }

    public static function sumAprovals($data)
    {
        $jobs = self::baseQuery($data);
        if (!isset($data['attendance']) || count($data['attendance']) <= 0) {
            $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.final_value) as sum'))->where('status_id', 3)->first();
        } else {
            $result = $jobs->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(
                    CASE
                        WHEN (comission_percentage IS NOT NULL AND comission_percentage > 0) THEN
                            CASE
                                WHEN 
                                    (attendance_comission_id IN (' . implode(',', $data['attendance']) . ') AND 
                                     attendance_id IN (' . implode(',', $data['attendance']) . ')) THEN final_value
                                WHEN attendance_id IN (' . implode(',', $data['attendance']) . ') AND attendance_comission_id NOT IN (' . implode(',', $data['attendance']) . ') THEN final_value * ((100 - comission_percentage) / 100)
                                WHEN attendance_comission_id IN (' . implode(',', $data['attendance']) . ') and attendance_id NOT IN (' . implode(',', $data['attendance']) . ') THEN final_value * (comission_percentage / 100)
                                ELSE final_value
                            END
                        ELSE final_value
                    END
                ) as sum')
            )->where('status_id', 3)->first();
        }

        return ["sum" => $result->sum != null ? $result->sum : 0, "count" => $result->count > 0 ? $result->count : 0];
    }

    public static function sumAprovalsGeneral()
    {
        $jobs = Job::where('time_to_aproval', '<>', null);
        $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.time_to_aproval) as sum'))->first();
        $count = $result->count > 0 ? $result->count : 0;
        $sum = $result->sum > 0 ? $result->sum : 0;

        return round($sum / $count);
    }

    public static function sumStandby($data)
    {
        $jobs = self::baseQuery($data);

        if (!isset($data['attendance']) || count($data['attendance']) <= 0) {
            $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.final_value) as sum'))->where('status_id', 1)->first();
        } else {
            $result = $jobs->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(
                    CASE
                        WHEN (comission_percentage IS NOT NULL AND comission_percentage > 0) THEN
                            CASE
                                WHEN 
                                    (attendance_comission_id IN (' . implode(',', $data['attendance']) . ') AND 
                                     attendance_id IN (' . implode(',', $data['attendance']) . ')) THEN final_value
                                WHEN attendance_id IN (' . implode(',', $data['attendance']) . ') AND attendance_comission_id NOT IN (' . implode(',', $data['attendance']) . ') THEN final_value * ((100 - comission_percentage) / 100)
                                WHEN attendance_comission_id IN (' . implode(',', $data['attendance']) . ') and attendance_id NOT IN (' . implode(',', $data['attendance']) . ') THEN final_value * (comission_percentage / 100)
                                ELSE final_value
                            END
                        ELSE final_value
                    END
                ) as sum')
            )->where('status_id', 1)->first();
        }

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

        if (!isset($data['attendance']) || count($data['attendance']) <= 0) {
            $result = $baseQuery->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.final_value) as sum'))->where('status_id', 3)->groupBy(DB::raw('MONTH(created_at)'))->get();
        } else {
            $result = $baseQuery->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(
                    CASE
                        WHEN (comission_percentage IS NOT NULL AND comission_percentage > 0) THEN
                            CASE
                                WHEN 
                                    (attendance_comission_id IN (' . implode(',', $data['attendance']) . ') AND 
                                     attendance_id IN (' . implode(',', $data['attendance']) . ')) THEN final_value
                                WHEN attendance_id IN (' . implode(',', $data['attendance']) . ') AND attendance_comission_id NOT IN (' . implode(',', $data['attendance']) . ') THEN final_value * ((100 - comission_percentage) / 100)
                                WHEN attendance_comission_id IN (' . implode(',', $data['attendance']) . ') and attendance_id NOT IN (' . implode(',', $data['attendance']) . ') THEN final_value * (comission_percentage / 100)
                                ELSE final_value
                            END
                        ELSE final_value
                    END
                ) as sum')
            )->where('status_id', 3)->groupBy(DB::raw('MONTH(created_at)'))->get();
        }

        if ($result->isEmpty()) {
            return ["amount" => 0, "value" => 0, "valueNumber" => 0];
        }

        $totalJobsApproved = 0;
        $totalValueJobsApproved = 0;
        foreach ($result as $job) {
            $totalJobsApproved += $job->count;
            $totalValueJobsApproved += $job->sum;
        }

        // Calcular a média de jobs aprovados por mês
        $averageJobsPerMonth = round($totalJobsApproved / $monthsPassed);
        $totalValueJobsApprovedNumber = $totalValueJobsApproved / $monthsPassed;
        $totalValueJobsApproved = number_format(($totalValueJobsApproved / $monthsPassed), 2, ',', '.');

        return ["amount" => $averageJobsPerMonth, "value" => $totalValueJobsApproved, "valueNumber" => $totalValueJobsApprovedNumber];
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

    public static function averageAdvancedJobsPerMonth($data)
    {
        $jobs = self::baseQuery($data);

        if (!isset($data['attendance']) || count($data['attendance']) <= 0) {
            $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.final_value) as sum'))->where('status_id', 5)->first();
        } else {
            $result = $jobs->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(
                    CASE
                        WHEN (comission_percentage IS NOT NULL AND comission_percentage > 0) THEN
                            CASE
                                WHEN 
                                    (attendance_comission_id IN (' . implode(',', $data['attendance']) . ') AND 
                                     attendance_id IN (' . implode(',', $data['attendance']) . ')) THEN final_value
                                WHEN attendance_id IN (' . implode(',', $data['attendance']) . ') AND attendance_comission_id NOT IN (' . implode(',', $data['attendance']) . ') THEN final_value * ((100 - comission_percentage) / 100)
                                WHEN attendance_comission_id IN (' . implode(',', $data['attendance']) . ') and attendance_id NOT IN (' . implode(',', $data['attendance']) . ') THEN final_value * (comission_percentage / 100)
                                ELSE final_value
                            END
                        ELSE final_value
                    END
                ) as sum')
            )->where('status_id', 5)->first();
        }

        if (!$result) {
            return ["sum" => number_format(0, 2, ',', '.'), "count" => 0];
        }
        return ["sum" => number_format($result->sum, 2, ',', '.'), "count" => $result->count];
    }

    public static function averageTicket($data)
    {
        $total_value = self::sumBudgetValue($data);

        if ($total_value['count'] > 0) {
            return $total_value['sum'] / $total_value['count'];
        } else {
            return 0;
        }
    }

    public static function averageTicketRef($data)
    {
        $total_value = self::sumBudgetValueRef($data);

        if ($total_value['count'] > 0) {
            return $total_value['sum'] / $total_value['count'];
        } else {
            return 0;
        }
    }

    public function biggestSale($data)
    {
        $sale = Job::select('final_value')->where(
            function ($query) {
                $query->where('attendance_id', User::logged()->employee->id)
                    ->orWhere('attendance_comission_id', User::logged()->employee->id);
            }
        )->orderBy('final_value', 'desc')->first();

        if ($sale) {
            return $sale->final_value;
        } else {
            return 0;
        }
    }

    public function biggestSaleRef($data)
    {
        $sale = Job::select('final_value')->orderBy('final_value', 'desc')->first();

        if ($sale) {
            return $sale->final_value;
        } else {
            return 0;
        }
    }
}
