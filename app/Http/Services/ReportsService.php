<?php

namespace App\Http\Service;

use App\Goal;
use App\Job;
use App\JobStatus;
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
        $jobs = Job::selectRaw('job.*');

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
        if (isset($data['userFilter']) && $data['userFilter'] == false) {
            $jobs = self::queryNoUserFilter($data);
        } else {
            $jobs = self::baseQuery($data);
        }

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
        if (isset($data['userFilter']) && $data['userFilter'] == false) {
            $jobs = self::queryNoUserFilter($data);
        } else {
            $jobs = self::baseQuery($data);
        }

        $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.time_to_aproval) as sum'))->first();
        $count = $result->count > 0 ? $result->count : 0;
        $sum = $result->sum > 0 ? $result->sum : 0;

        return $count > 0 ? round($sum / $count) : 0;
    }

    public static function sumGeneralTimeToAproval($data)
    {
        if (isset($data['userFilter']) && $data['userFilter'] == false) {
            $jobs = self::queryNoUserFilter($data);
        } else {
            $jobs = self::baseQuery($data);
        }
        $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.time_to_aproval) as sum'))->first();
        $count = $result->count > 0 ? $result->count : 0;
        $sum = $result->sum > 0 ? $result->sum : 0;

        return round($sum / $count);
    }

    public static function sumAprovals($data)
    {
        if (isset($data['userFilter']) && $data['userFilter'] == false) {
            $jobs = self::queryNoUserFilter($data);
        } else {
            $jobs = self::baseQuery($data);
        }

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
        if (isset($data['userFilter']) && $data['userFilter'] == false) {
            $jobs = self::queryNoUserFilter($data);
        } else {
            $jobs = self::baseQuery($data);
        }

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
        if (isset($data['userFilter']) && $data['userFilter'] == false) {
            $jobs = self::queryNoUserFilter($data);
        } else {
            $jobs = self::baseQuery($data);
        }

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

        if (isset($data['userFilter']) && $data['userFilter'] == false) {
            $baseQuery = self::queryNoUserFilter($data);
        } else {
            $baseQuery = self::baseQuery($data);
        }

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
        if (isset($data['userFilter']) && $data['userFilter'] == false) {
            $jobs = self::queryNoUserFilter($data);
        } else {
            $jobs = self::baseQuery($data);
        }

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

    public function biggestSale($data)
    {
        $sale = Job::selectRaw('job.*, CAST(final_value AS DECIMAL) AS final_value_numeric')
            ->where('status_id', 3)
            ->orderBy('final_value_numeric', 'desc');



        if (isset($data['date_init'])) {
            $sale->where('created_at', '>=', Carbon::parse($data['date_init'])->format('Y-m-d'));
        } else {
            $sale->where('created_at', '>=', Carbon::now()->startOfYear()->format('Y-m-d'));
        }

        if (isset($data['date_end'])) {
            $sale->where('created_at', '<=', Carbon::parse($data['date_end'])->format('Y-m-d'));
        } else {
            $sale->where('created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'));
        }

        $sale = $sale->first();

        if ($sale) {
            return $sale->final_value;
        } else {
            return 0;
        }
    }

    public function biggestSaleRef($data)
    {
        $sale = Job::select('final_value')->orderBy('final_value', 'desc')->first();

        return $sale->final_value ?? 0;
    }

    public function myLastJobApproved()
    {
        $job = Job::where('attendance_id', User::logged()->employee->id)->where('status_id', 3)->orderBy('status_updated_at', 'desc')->with('client')->first();

        if ($job) {
            if (isset($job->client)) {
                $clientName = ($job->client->fantasy_name ?? $job->client->name) . " | " . $job->event;
            } else {
                $clientName = ($job->agency->fantasy_name ?? $job->agency->name) . " | " . $job->event;
            }
        } else {
            $clientName = "";
        }

        return $clientName;
    }

    public function LastJobApproved()
    {
        $job = Job::where('status_id', 3)->orderBy('status_updated_at', 'desc')->with('client')->with('agency')->first();

        if ($job) {
            if (isset($job->client)) {
                $clientName = ($job->client->fantasy_name ?? $job->client->name) . " | " . $job->event;
            } else {
                $clientName = ($job->agency->fantasy_name ?? $job->agency->name) . " | " . $job->event;
            }
        } else {
            $clientName = "";
        }

        return $clientName;
    }

    public function SaleRanking()
    {
        // Vendedor com maior venda no primeiro trimestre do ano
        $firstQuarterTopSeller = Job::selectRaw('job.*')
            ->select('attendance_id', DB::raw('SUM(final_value) as total_sales'))
            ->with('attendance:id,name')
            ->whereYear('created_at', '=', date('Y'))
            ->whereBetween(DB::raw('QUARTER(created_at)'), [1, 1])
            ->where('status_id', 3)
            ->groupBy('attendance_id')
            ->orderByDesc('total_sales')
            ->first();

        // Vendedor com maior venda no segundo trimestre do ano
        $secondQuarterTopSeller = Job::selectRaw('job.*')
            ->select('attendance_id', DB::raw('SUM(final_value) as total_sales'))
            ->with('attendance:id,name')
            ->whereYear('created_at', '=', date('Y'))
            ->whereBetween(DB::raw('QUARTER(created_at)'), [2, 2])
            ->where('status_id', 3)
            ->groupBy('attendance_id')
            ->orderByDesc('total_sales')
            ->first();

        // Vendedor com maior venda no terceiro trimestre do ano
        $thirdQuarterTopSeller = Job::selectRaw('job.*')
            ->select('attendance_id', DB::raw('SUM(final_value) as total_sales'))
            ->with('attendance:id,name')
            ->whereYear('created_at', '=', date('Y'))
            ->whereBetween(DB::raw('QUARTER(created_at)'), [3, 3])
            ->where('status_id', 3)
            ->groupBy('attendance_id')
            ->orderByDesc('total_sales')
            ->first();

        // Vendedor com maior venda no quarto trimestre do ano
        $fourthQuarterTopSeller = Job::selectRaw('job.*')
            ->select('attendance_id', DB::raw('SUM(final_value) as total_sales'))
            ->with('attendance:id,name')
            ->whereYear('created_at', '=', date('Y'))
            ->whereBetween(DB::raw('QUARTER(created_at)'), [4, 4])
            ->where('status_id', 3)
            ->groupBy('attendance_id')
            ->orderByDesc('total_sales')
            ->first();

        // Vendedor com maior venda nos últimos 12 meses
        $last12MonthsTopSeller = Job::selectRaw('job.*')
            ->with('attendance:id,name')
            ->select('attendance_id', DB::raw('SUM(final_value) as total_sales'))
            ->whereBetween('created_at', [now()->subMonths(12), now()])
            ->where('status_id', 3)
            ->groupBy('attendance_id')
            ->orderByDesc('total_sales')
            ->first();

        return [
            "firstQuarterTopSeller" => $firstQuarterTopSeller,
            "secondQuarterTopSeller" => $secondQuarterTopSeller,
            "thirdQuarterTopSeller" => $thirdQuarterTopSeller,
            "fourthQuarterTopSeller" => $fourthQuarterTopSeller,
            "last12MonthsTopSeller" => $last12MonthsTopSeller
        ];
    }

    public function GetApproveds($data)
    {
        $jobs =  Job::where('status_id', 3);
        $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.final_value) as sum'));

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
        $result = $jobs->first();
        return $result;
    }

    public function GetLastApproveds($data)
    {
        $jobs = Job::where('status_id', 3);
        $jobs->select("*")->orderBy('created_at', 'desc');;

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
        $result = $jobs->get();
        return $result;
    }

    public function GetByCategories($data)
    {
        $jobs = Job::where('status_id', "<>", 2)->with('job_type')
            ->select('job_type_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(final_value) as sum'))
            ->groupBy('job_type_id');
    
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
    
        $results = $jobs->get();
    
        // Processar os resultados para o formato desejado
        $formattedResults = [];
        $totalCount = 0;
        $totalSum = 0;
        foreach ($results as $result) {
            $formattedResults[$result->job_type->description] = [
                'job_type_id' => $result->job_type_id,
                'count' => $result->count,
                'sum' => $result->sum,
            ];
            $totalCount = $totalCount + $result->count;
            $totalSum = $totalSum + $result->sum;
        }

        $formattedResults["totals"] = ["totalCount" => $totalCount, "totalSum" => $totalSum];

        return $formattedResults;
    }

    public function GetAdvanceds($data)
    {
        $jobs =  Job::where('status_id', 5);

        $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.final_value) as sum'));


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
        $result = $jobs->first();

        return $result;
    }

    public function GetStandbys($data)
    {
        $jobs =  Job::where('status_id', 1);

        $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.final_value) as sum'));


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
        $result = $jobs->first();

        return $result;
    }

    public function GetReproveds($data)
    {
        $jobs =  Job::where('status_id', 4);

        $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.final_value) as sum'));


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
        $result = $jobs->first();

        return $result;
    }

    public function GetAdjusts($data)
    {
        $jobs = Job::where('status_id', 1);
        $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.final_value) as sum'))
            ->whereHas('tasks', function ($query) {
                $query->whereHas('job_activity', function ($innerQuery) {
                    $innerQuery->where('description', 'like', '%Modificação%');
                });
            })
            ->with('tasks.job_activity', 'tasks.responsible');

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
        $result = $jobs->first();

        return $result;
    }

    public function GetGoals()
    {

        $CurrentMonthGoal = $this->getCurrentMonthGoal();
        $Last3MonthsGoals = $this->getLast3MonthsGoals();
        $Last12MonthsGoals = $this->getLast12MonthsGoals();
        $CurrentYearGoals = $this->getCurrentYearGoals();

        $CurrentMonthValue = $this->GetApproveds(['date_init' => Carbon::now()->startOfMonth()->format('Y-m-d'), 'date_end' => Carbon::now()->endOfQuarter()->format('Y-m-d')]);
        $Last3MonthsValue = $this->GetApproveds(['date_init' => Carbon::now()->startOfQuarter()->format('Y-m-d'), 'date_end' => Carbon::now()->endOfQuarter()->format('Y-m-d')]);
        $Last12MonthsValue = $this->GetApproveds(['date_init' => Carbon::now()->startOfMonth()->subMonth(11)->format('Y-m-d'), 'date_end' => Carbon::now()->endOfMonth()->format('Y-m-d')]);
        $CurrentYearValue = $this->GetApproveds(['date_init' => Carbon::now()->startOfYear()->format('Y-m-d'), 'date_end' => Carbon::now()->endOfYear()->format('Y-m-d')]);


        $goals = [
            "ultimos_doze_meses" => [
                "porcentagem" => (($Last12MonthsValue->sum * 100) / $Last12MonthsGoals) > 100 ? 100 : (($Last12MonthsValue->sum * 100) / $Last12MonthsGoals),
                "atual" =>  $Last12MonthsValue->sum,
                "meta" =>  $Last12MonthsGoals
            ],
            "mes" => [
                "porcentagem" => (($CurrentMonthValue->sum * 100) / $CurrentMonthGoal) > 100 ? 100 : (($CurrentMonthValue->sum * 100) / $CurrentMonthGoal),
                "atual" => $CurrentMonthValue->sum,
                "meta" =>  $CurrentMonthGoal
            ],
            "quarter" => [
                "porcentagem" => (($Last3MonthsValue->sum * 100) / $Last3MonthsGoals) > 100 ? 100 : (($Last3MonthsValue->sum * 100) / $Last3MonthsGoals),
                "atual" =>  $Last3MonthsValue->sum,
                "meta" =>  $Last3MonthsGoals
            ],
            "anual" => [
                "porcentagem" => (($CurrentYearValue->sum * 100) / $CurrentYearGoals) > 100 ? 100 : (($CurrentYearValue->sum * 100) / $CurrentYearGoals),
                "atual" =>  $CurrentYearValue->sum,
                "meta" =>  $CurrentYearGoals
            ]
        ];

        return $goals;
    }

    public function getGoal($mount)
    {
        $currentYear = date('Y');
        $goals = Goal::where('month', $mount)->where('year', $currentYear)->sum('value');
        $realized = Job::where('status_id', 3)->whereYear('status_updated_at', '=', $currentYear)->whereMonth('status_updated_at', '=', $mount)->sum('final_value');

        $goals == 0 ? 1 : $goals;
        $realized == 0 ? 1 : $realized;
        return ["goals" => $goals, "realized" => $realized];
    }

    public function getCurrentMonthGoal()
    {
        $currentMonth = date('n');
        $currentYear = date('Y');

        $goals = Goal::where('month', $currentMonth)->where('year', $currentYear)->sum('value');

        return $goals == 0 ? 1 : $goals;
    }

    public function getLast3MonthsGoals()
    {
        $currentMonth = date('n');
        $currentYear = date('Y');

        $goals = Goal::where(function ($query) use ($currentMonth, $currentYear) {
            for ($i = 0; $i < 3; $i++) {
                $query->orWhere(function ($subquery) use ($currentMonth, $currentYear, $i) {
                    $subquery->where('month', $currentMonth - $i)->where('year', $currentYear);
                });
            }
        })->sum('value');

        return $goals == 0 ? 1 : $goals;
    }

    public function getLast12MonthsGoals()
    {

        $currentMonth = date('n');
        $currentYear = date('Y');

        $goals = Goal::where(function ($query) use ($currentMonth, $currentYear) {
            $query->orWhere(function ($subquery) use ($currentMonth, $currentYear) {
                $subquery->where('year', $currentYear)->where('month', '=', $currentMonth);
            });

            for ($i = 1; $i <= 12; $i++) {
                $query->orWhere(function ($subquery) use ($currentMonth, $currentYear, $i) {
                    $subquery->where('year', $currentYear - (($currentMonth - $i) < 0 ? 1 : 0))
                        ->where('month', (($currentMonth - $i + 12) % 12) + 1);
                });
            }
        })->sum('value');

        return $goals == 0 ? 1 : $goals;
    }

    public function getCurrentYearGoals()
    {
        $currentYear = date('Y');

        $goals = Goal::where('year', $currentYear)->sum('value');

        return $goals == 0 ? 1 : $goals;
    }
}
