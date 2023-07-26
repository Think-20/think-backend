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

        $jobs = $jobs->paginate(5);

        $total_value = self::sumBudgetValue($data);
        if ($total_value) {
            $average_ticket = $total_value['sum'] / $total_value['count'];
        } else {
            $total_value['sum'] = 0;
            $average_ticket = 0;
        }
        $standby = self::sumStandby($data);
        if ($standby) {
            $countStandby = $standby['count'];
            $valueStandby = $standby['sum'];
        } else {
            $countStandby = 0;
        }
        $stand = 0;
        $cenografia = 0;
        $pdv = 0;
        $showroom = 0;
        $outsider = 0;
        $types = self::getTypes($data);
        $averageTimeToAproval = self::sumTimeToAproval($data);
        $valueAprovals = self::sumAprovals($data);
        $conversionRate = $valueAprovals / $total_value['sum'];

        return response()->json([
            "jobs" => $jobs,
            "total_value" => number_format($total_value['sum'], 2, ',', '.'),
            "average_ticket" => number_format($average_ticket, 2, ',', '.'),
            "averate_time_to_aproval" => $averageTimeToAproval,
            "aprovals_value" => number_format($valueAprovals, 2, ',', '.'),
            "conversion_rate" => number_format($conversionRate, 2, ',', '.'),
            "standby_count" => $countStandby,
            "types" => $types
        ]);
    }

    public static function sumBudgetValue($data)
    {
        $name = $data['name'] ?? null;
        $initialDate = $data['dateInit'] ?? null;
        $finalDate = $data['dateEnd'] ?? null;

        $result = DB::table('job')
            ->leftJoin('client', 'job.client_id', '=', 'client.id')
            ->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.budget_value) as sum'))
            ->where(function ($query) use ($name) {
                $query->where(function ($query) use ($name) {
                    $query->where('client.fantasy_name', 'LIKE', '%' . $name . '%')
                        ->orWhere('client.name', 'LIKE', '%' . $name . '%');
                })
                    ->orWhere('not_client', 'like', '%' . $name . '%');
            });

        if ($initialDate && !$finalDate) {
            $result->where('job.created_at', '>=', $initialDate . ' 00:00:00')
                ->where('job.created_at', '<=', $initialDate . ' 23:59:59');
        } elseif (!$initialDate && $finalDate) {
            $result->where('job.created_at', '>=', $finalDate . ' 00:00:00')
                ->where('job.created_at', '<=', $finalDate . ' 23:59:59');
        } elseif ($initialDate && $finalDate) {
            $result->where('job.created_at', '>=', $initialDate . ' 00:00:00')
                ->where('job.created_at', '<=', $finalDate . ' 23:59:59');
        }
        $result = $result->first();

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
        $name = $data['name'] ?? null;
        $initialDate = $data['dateInit'] ?? null;
        $finalDate = $data['dateEnd'] ?? null;

        $result = DB::table('job')
            ->leftJoin('client', 'job.client_id', '=', 'client.id')
            ->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.time_to_aproval) as sumTimeToAproval'))
            ->where(function ($query) use ($name) {
                $query->where(function ($query) use ($name) {
                    $query->where('client.fantasy_name', 'LIKE', '%' . $name . '%')
                        ->orWhere('client.name', 'LIKE', '%' . $name . '%');
                })
                    ->orWhere('not_client', 'like', '%' . $name . '%');
            });

        if ($initialDate && !$finalDate) {
            $result->where('job.created_at', '>=', $initialDate . ' 00:00:00')
                ->where('job.created_at', '<=', $initialDate . ' 23:59:59');
        } elseif (!$initialDate && $finalDate) {
            $result->where('job.created_at', '>=', $finalDate . ' 00:00:00')
                ->where('job.created_at', '<=', $finalDate . ' 23:59:59');
        } elseif ($initialDate && $finalDate) {
            $result->where('job.created_at', '>=', $initialDate . ' 00:00:00')
                ->where('job.created_at', '<=', $finalDate . ' 23:59:59');
        }

        $result = $result->first();

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
        $name = $data['name'] ?? null;
        $initialDate = $data['dateInit'] ?? null;
        $finalDate = $data['dateEnd'] ?? null;

        $result = DB::table('job')
            ->leftJoin('client', 'job.client_id', '=', 'client.id')
            ->select(DB::raw('SUM(job.budget_value) as sum'))
            ->where(function ($query) use ($name) {
                $query->where(function ($query) use ($name) {
                    $query->where('client.fantasy_name', 'LIKE', '%' . $name . '%')
                        ->orWhere('client.name', 'LIKE', '%' . $name . '%');
                })
                    ->orWhere('not_client', 'like', '%' . $name . '%');
            })
            ->where('status_id', 3);

        if ($initialDate && !$finalDate) {
            $result->where('job.created_at', '>=', $initialDate . ' 00:00:00')
                ->where('job.created_at', '<=', $initialDate . ' 23:59:59');
        } elseif (!$initialDate && $finalDate) {
            $result->where('job.created_at', '>=', $finalDate . ' 00:00:00')
                ->where('job.created_at', '<=', $finalDate . ' 23:59:59');
        } elseif ($initialDate && $finalDate) {
            $result->where('job.created_at', '>=', $initialDate . ' 00:00:00')
                ->where('job.created_at', '<=', $finalDate . ' 23:59:59');
        }
        $result = $result->first();

        $sum = $result->sum;

        if ($sum != null) {
            return $sum;
        } else {
            return 0;
        }
    }

    public static function sumStandby($data)
    {
        $name = $data['name'] ?? null;
        $initialDate = $data['dateInit'] ?? null;
        $finalDate = $data['dateEnd'] ?? null;

        $result = DB::table('job')
            ->leftJoin('client', 'job.client_id', '=', 'client.id')
            ->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(job.budget_value) as sum'))
            ->where(function ($query) use ($name) {
                $query->where(function ($query) use ($name) {
                    $query->where('client.fantasy_name', 'LIKE', '%' . $name . '%')
                        ->orWhere('client.name', 'LIKE', '%' . $name . '%');
                })
                    ->orWhere('not_client', 'like', '%' . $name . '%');
            })
            ->where('status_id', 1);

        if ($initialDate && !$finalDate) {
            $result->where('job.created_at', '>=', $initialDate . ' 00:00:00')
                ->where('job.created_at', '<=', $initialDate . ' 23:59:59');
        } elseif (!$initialDate && $finalDate) {
            $result->where('job.created_at', '>=', $finalDate . ' 00:00:00')
                ->where('job.created_at', '<=', $finalDate . ' 23:59:59');
        } elseif ($initialDate && $finalDate) {
            $result->where('job.created_at', '>=', $initialDate . ' 00:00:00')
                ->where('job.created_at', '<=', $finalDate . ' 23:59:59');
        }
        $result = $result->first();

        $sum = $result->sum;
        $count = $result->count;

        if ($sum != null) {
            return ["sum" => $sum, "count" => $count];
        } else {
            return false;
        }
    }

    public static function getTypes($data)
    {
        $name = $data['name'] ?? null;
        $initialDate = $data['dateInit'] ?? null;
        $finalDate = $data['dateEnd'] ?? null;

        $baseQuery = Job::selectRaw('job.*')
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
            $baseQuery->whereHas('client', function ($query) use ($name) {
                $query->where('fantasy_name', 'LIKE', '%' . $name . '%');
                $query->orWhere('name', 'LIKE', '%' . $name . '%');
            });
            $baseQuery->orWhere('not_client', 'LIKE', '%' . $name . '%');
        }

        if ($initialDate && !$finalDate) {
            $baseQuery->where('created_at', '>=', $initialDate . ' 00:00:00')
                ->where('created_at', '<=', $initialDate . ' 23:59:59');
        } elseif (!$initialDate && $finalDate) {
            $baseQuery->where('created_at', '>=', $finalDate . ' 00:00:00')
                ->where('created_at', '<=', $finalDate . ' 23:59:59');
        } elseif ($initialDate && $finalDate) {
            $baseQuery->where('created_at', '>=', $initialDate . ' 00:00:00')
                ->where('created_at', '<=', $finalDate . ' 23:59:59');
        }

        $countStand = clone $baseQuery;
        $countStand = $countStand->whereHas('job_type', function ($query) {
            $query->where('description', 'Stand');
        })->count();

        $countShowroom = clone $baseQuery;
        $countShowroom = $countShowroom->whereHas('job_type', function ($query) {
            $query->where('description', 'Showroom');
        })->count();

        $countCenografia = clone $baseQuery;
        $countCenografia = $countCenografia->whereHas('job_type', function ($query) {
            $query->where('description', 'Cenografia');
        })->count();

        $countPdv = clone $baseQuery;
        $countPdv = $countPdv->whereHas('job_type', function ($query) {
            $query->where('description', 'Pdv');
        })->count();

        $countOutsider = clone $baseQuery;
        $countOutsider = $countOutsider->whereHas('job_type', function ($query) {
            $query->where('description', 'Outsider');
        })->count();

        $jobs = $baseQuery->get();

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
}
