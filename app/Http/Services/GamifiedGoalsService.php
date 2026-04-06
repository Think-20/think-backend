<?php

namespace App\Http\Services;

use App\Job;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Metas gamificadas por período: busca todos os jobs do usuário no intervalo,
 * escala as metas pela quantidade de meses e retorna um único objeto com
 * todas as métricas e % de cumprimento.
 */
class GamifiedGoalsService
{
    const STATUS_APPROVED = 3;
    const INTERNAL_VALUE_PER_MONTH = 2_000_000; // 2M em projetos internos / mês

    /**
     * Retorna quantidade de meses no intervalo (inclusive).
     * Ex: 01/10/2025 a 31/12/2025 = 3 meses.
     */
    public static function monthsInRange(string $dateInit, string $dateEnd): int
    {
        $start = Carbon::parse($dateInit)->startOfDay();
        $end = Carbon::parse($dateEnd)->startOfDay();
        $months = $start->diffInMonths($end) + 1;
        return max(1, (int) $months);
    }

    /**
     * Trimestres no intervalo (arredonda pra cima). Ex: 3 meses = 1 trimestre, 4 meses = 2.
     */
    public static function quartersInRange(string $dateInit, string $dateEnd): int
    {
        $months = self::monthsInRange($dateInit, $dateEnd);
        return (int) ceil($months / 3);
    }

    /**
     * Busca todos os jobs do usuário no período (uma única query com job_activity).
     */
    public static function getJobsForPeriod(string $dateInit, string $dateEnd, int $attendanceId): Collection
    {
        return Job::query()
            ->with('job_activity')
            ->whereBetween(DB::raw('DATE(job.created_at)'), [$dateInit, $dateEnd])
            ->where(function ($q) use ($attendanceId) {
                $q->where('job.attendance_id', $attendanceId)
                    ->orWhere('job.attendance_comission_id', $attendanceId);
            })
            ->get();
    }

    private static function isInternal(Job $job): bool
    {
        $desc = $job->job_activity->description ?? '';
        return stripos($desc, 'externo') === false;
    }

    private static function jobValue(Job $job): float
    {
        $v = $job->final_value ?? $job->budget_value ?? 0;
        return (float) $v;
    }

    public static function evaluateForPeriod(string $dateInit, string $dateEnd, int $attendanceId): array
    {
        $dateInit = Carbon::parse($dateInit)->format('Y-m-d');
        $dateEnd = Carbon::parse($dateEnd)->format('Y-m-d');

        $months = self::monthsInRange($dateInit, $dateEnd);
        $quarters = self::quartersInRange($dateInit, $dateEnd);

        $jobs = self::getJobsForPeriod($dateInit, $dateEnd, $attendanceId);

        $internal = $jobs->filter(fn (Job $j) => self::isInternal($j));
        $external = $jobs->filter(fn (Job $j) => !self::isInternal($j));
        $approved = $jobs->where('status_id', self::STATUS_APPROVED);
        $internalApproved = $internal->where('status_id', self::STATUS_APPROVED);
        $externalApproved = $external->where('status_id', self::STATUS_APPROVED);

        $goals = [];

        // 0 - 2 milhões em projetos internos / mês
        $targetValue = self::INTERNAL_VALUE_PER_MONTH * $months;
        $currentValue = $internalApproved->sum(fn (Job $j) => self::jobValue($j));
        $pct = $targetValue > 0 ? min(100, round(($currentValue / $targetValue) * 100, 1)) : 0;
        $goals[] = [
            'key' => 'internal_value_per_month',
            'label' => '2 milhões em projetos internos / mês',
            'target' => $targetValue,
            'target_label' => number_format($targetValue / 1_000_000, 1, ',', '.') . 'M (' . $months . ' ' . ($months === 1 ? 'mês' : 'meses') . ')',
            'current' => $currentValue,
            'current_label' => number_format($currentValue, 0, ',', '.'),
            'percentage' => $pct,
            'achieved' => $currentValue >= $targetValue,
        ];

        // 1 - 2 projetos internos acima de 150k / mês
        $targetCount = 2 * $months;
        $currentCount = $internalApproved->filter(fn (Job $j) => self::jobValue($j) >= 150_000)->count();
        $pct = $targetCount > 0 ? min(100, round(($currentCount / $targetCount) * 100, 1)) : 0;
        $goals[] = [
            'key' => 'internal_projects_above_150k',
            'label' => '2 projetos internos acima de 150k / mês',
            'target' => $targetCount,
            'target_label' => $targetCount . ' (' . $months . ' ' . ($months === 1 ? 'mês' : 'meses') . ')',
            'current' => $currentCount,
            'percentage' => $pct,
            'achieved' => $currentCount >= $targetCount,
        ];

        // 2 - 1 projeto interno acima de 300k / mês
        $targetCount = 1 * $months;
        $currentCount = $internalApproved->filter(fn (Job $j) => self::jobValue($j) >= 300_000)->count();
        $pct = $targetCount > 0 ? min(100, round(($currentCount / $targetCount) * 100, 1)) : 0;
        $goals[] = [
            'key' => 'internal_projects_above_300k',
            'label' => '1 projeto interno acima de 300k / mês',
            'target' => $targetCount,
            'target_label' => $targetCount . ' (' . $months . ' ' . ($months === 1 ? 'mês' : 'meses') . ')',
            'current' => $currentCount,
            'percentage' => $pct,
            'achieved' => $currentCount >= $targetCount,
        ];

        // 3 - 1 projeto interno acima de 600k / mês
        $targetCount = 1 * $months;
        $currentCount = $internalApproved->filter(fn (Job $j) => self::jobValue($j) >= 600_000)->count();
        $pct = $targetCount > 0 ? min(100, round(($currentCount / $targetCount) * 100, 1)) : 0;
        $goals[] = [
            'key' => 'internal_projects_above_600k',
            'label' => '1 projeto interno acima de 600k / mês',
            'target' => $targetCount,
            'target_label' => $targetCount . ' (' . $months . ' ' . ($months === 1 ? 'mês' : 'meses') . ')',
            'current' => $currentCount,
            'percentage' => $pct,
            'achieved' => $currentCount >= $targetCount,
        ];

        // 4 - 1 projeto interno acima de 1500k / mês
        $targetCount = 1 * $months;
        $currentCount = $internalApproved->filter(fn (Job $j) => self::jobValue($j) >= 1_500_000)->count();
        $pct = $targetCount > 0 ? min(100, round(($currentCount / $targetCount) * 100, 1)) : 0;
        $goals[] = [
            'key' => 'internal_projects_above_1500k',
            'label' => '1 projeto interno acima de 1500k / mês',
            'target' => $targetCount,
            'target_label' => $targetCount . ' (' . $months . ' ' . ($months === 1 ? 'mês' : 'meses') . ')',
            'current' => $currentCount,
            'percentage' => $pct,
            'achieved' => $currentCount >= $targetCount,
        ];

        // 5 - Presencial 2x por semana (não avaliado)
        $goals[] = [
            'key' => 'presencial_2x_week',
            'label' => 'Ir presencialmente 2x por semana',
            'target' => null,
            'target_label' => null,
            'current' => null,
            'percentage' => 0,
            'achieved' => false,
            'not_evaluated' => true,
            'message' => 'Avaliação ainda não implementada.',
        ];

        // 6 - Conversão 15% jobs externos (razão no período)
        $totalExt = $external->count();
        $approvedExt = $externalApproved->count();
        $conversionExt = $totalExt > 0 ? round(($approvedExt / $totalExt) * 100, 1) : 0;
        $targetPct = 15;
        $pct = $targetPct > 0 ? round(($conversionExt / $targetPct) * 100, 1) : 0;
        $goals[] = [
            'key' => 'conversion_external_15',
            'label' => 'Conversão de 15% de Jobs Externos',
            'target' => $targetPct . '%',
            'target_label' => $targetPct . '%',
            'current' => $conversionExt . '%',
            'current_raw' => $conversionExt,
            'total_jobs' => $totalExt,
            'approved_jobs' => $approvedExt,
            'percentage' => $pct,
            'achieved' => $conversionExt >= $targetPct,
        ];

        // 7 - Conversão 25% jobs internos
        $totalInt = $internal->count();
        $approvedInt = $internalApproved->count();
        $conversionInt = $totalInt > 0 ? round(($approvedInt / $totalInt) * 100, 1) : 0;
        $targetPct = 25;
        $pct = $targetPct > 0 ? round(($conversionInt / $targetPct) * 100, 1) : 0;
        $goals[] = [
            'key' => 'conversion_internal_25',
            'label' => 'Conversão de 25% de Jobs Internos',
            'target' => $targetPct . '%',
            'target_label' => $targetPct . '%',
            'current' => $conversionInt . '%',
            'current_raw' => $conversionInt,
            'total_jobs' => $totalInt,
            'approved_jobs' => $approvedInt,
            'percentage' => $pct,
            'achieved' => $conversionInt >= $targetPct,
        ];

        // 8 - 6 aprovações mínimas por trimestre
        $targetApprovals = 6 * $quarters;
        $currentApprovals = $approved->count();
        $pct = $targetApprovals > 0 ? min(100, round(($currentApprovals / $targetApprovals) * 100, 1)) : 0;
        $goals[] = [
            'key' => 'min_approvals_per_quarter',
            'label' => '6 aprovações mínimas por trimestre',
            'target' => $targetApprovals,
            'target_label' => $targetApprovals . ' (' . $quarters . ' ' . ($quarters === 1 ? 'trimestre' : 'trimestres') . ')',
            'current' => $currentApprovals,
            'percentage' => $pct,
            'achieved' => $currentApprovals >= $targetApprovals,
        ];

        return [
            'period' => [
                'date_init' => $dateInit,
                'date_end' => $dateEnd,
                'months' => $months,
                'quarters' => $quarters,
            ],
            'attendance_id' => $attendanceId,
            'jobs_count' => $jobs->count(),
            'goals' => $goals,
        ];
    }
}
