<?php

namespace App\Http\Controllers;

use App\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public static function read(Request $request)
    {
        $data = $request->only([
            'dateInit',
            'dateEnd',
            'name',
            'status'
        ]);

        $jobs = self::baseQuery($data)->paginate(5);
        if($jobs->isEmpty()){
            return response()->json(["error" => false, "message" => "Jobs not found"]);
        }

        $total_value = self::sumBudgetValue($data);
        $average_ticket = $total_value ? $total_value['sum'] / $total_value['count'] : 0;

        $standby = self::sumStandby($data);
        $countStandby = $standby ? $standby['count'] : 0;
        $sumStandby = $standby ? $standby['sum'] : 0;

        $types = self::getTypes($data);
        $averageTimeToAproval = self::sumTimeToAproval($data);
        $valueAprovals = self::sumAprovals($data);
        $conversionRate = ceil(($valueAprovals / $total_value['sum']) *100);
        $averageJobsPerMonth = self::averageApprovedJobsPerMonth($data);

        return response()->json([
            "jobs" => $jobs,
            "total_value" => number_format($total_value['sum'], 2, ',', '.'),
            "average_ticket" => number_format($average_ticket, 2, ',', '.'),
            "averate_time_to_aproval" => $averageTimeToAproval,
            "aprovals_value" => number_format($valueAprovals, 2, ',', '.'),
            "conversion_rate" => $conversionRate . "%",
            "standby_projects" => ["amount" => $countStandby, "value" => $sumStandby],
            "types" => $types,
            "averageApprovedJobsPerMonth" => $averageJobsPerMonth
        ]);
    }

    private static function baseQuery($data)
    {
 
        $name = $data['name'] ?? null;
        $initialDate = $data['dateInit'] ?? null;
        $finalDate = $data['dateEnd'] ?? null;

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
            }]);

        if ($name) {
            $jobs->whereHas('client', function ($query) use ($name) {
                $query->where('fantasy_name', 'LIKE', '%' . $name . '%');
                $query->orWhere('name', 'LIKE', '%' . $name . '%');
            });
            $jobs->orWhere('not_client', 'LIKE', '%' . $name . '%');
        }

        if ($initialDate && !$finalDate) {
            $jobs->where('created_at', '>=', $initialDate . ' 00:00:00')
                ->where('created_at', '<=', $initialDate . ' 23:59:59');
        } elseif (!$initialDate && $finalDate) {
            $jobs->where('created_at', '>=', $finalDate . ' 00:00:00')
                ->where('created_at', '<=', $finalDate . ' 23:59:59');
        } elseif ($initialDate && $finalDate) {
            $jobs->where('created_at', '>=', $initialDate . ' 00:00:00')
                ->where('created_at', '<=', $finalDate . ' 23:59:59');
        }
        
        return $jobs;
    }

    public static function sumBudgetValue($data)
    {
        $jobs = self::baseQuery($data);

        $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.budget_value) as sum'))->first();

        $count = $result->count;
        $sum = $result->sum;

        if ($count > 0 && $sum != null) {
            return ["sum" => $sum, "count" => $count];
        } else {
            return false;
        }
    }

    public static function sumTimeToAproval($data)
    {
        $jobs = self::baseQuery($data);

        $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.time_to_aproval) as sumTimeToAproval'))->first();

        $count = $result->count;
        $sumTimeToAproval = $result->sumTimeToAproval;

        if ($sumTimeToAproval != null) {
            return ceil($sumTimeToAproval / $count);
        } else {
            return 0;
        }
    }

    public static function sumAprovals($data)
    {
        $jobs = self::baseQuery($data);

        $result = $jobs->select(DB::raw('SUM(job.budget_value) as sum'))->where('status_id', 3)->first();

        $sum = $result->sum;

        if ($sum != null) {
            return $sum;
        } else {
            return 0;
        }
    }

    public static function sumStandby($data)
    {
        $jobs = self::baseQuery($data);

        $result = $jobs->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.budget_value) as sum'))->where('status_id', 1)->first();

        $sum = $result->sum;
        $count = $result->count;

        if ($sum != null) {
            return ["sum" => number_format($sum, 2, ',', '.'), "count" => $count];
        } else {
            return false;
        }
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

    public static function averageApprovedJobsPerMonth()
    {
        // Obter a data de início (1º de janeiro do ano atual)
        $initialDate = date('Y') . '-01-01';

        // Obter a data atual
        $currentDate = date('Y-m-d');

        $jobs = Job::selectRaw('COUNT(*) as count, MONTH(created_at) as month, SUM(budget_value) as budget_value')
            ->where('status_id', 3)
            ->where('created_at', '>=', $initialDate)
            ->where('created_at', '<=', $currentDate)
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->get();
            // dd($jobs->isEmpty());
        if($jobs->isEmpty()){
            return ["averageJobsPerMonth" => 0, "totalValueJobsApproved" => 0];
        }
        // Contar a quantidade de meses desde janeiro até o mês atual
        $monthsPassed = date('n');

        // Somar a quantidade de jobs aprovados por mês
        $totalJobsApproved = 0;
        $totalValueJobsApproved = 0;
        foreach ($jobs as $job) {
            $totalJobsApproved += $job->count;
            $totalValueJobsApproved += $job->budget_value;
        }

        // Calcular a média de jobs aprovados por mês
        $averageJobsPerMonth = $totalJobsApproved / $monthsPassed;

        return ["amount" => ceil($averageJobsPerMonth), "value" => number_format($totalValueJobsApproved, 2, ',', '.')];
    }
}