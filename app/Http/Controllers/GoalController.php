<?php

namespace App\Http\Controllers;

use App\Goal;
use App\Http\Services\ReportsService;
use ArrayObject;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    private $reportsService;
    public function __construct(ReportsService $reportsService)
    {
        $this->reportsService = $reportsService;
    }

    public function createGoal(Request $request)
    {
        if ($request->month <=  0  || $request->month >= 13) {
            return response()->json(['error' => 'true', 'message' => 'Mes invalido'], 400);
        }

        if (strlen($request->year) !== 4) {
            return response()->json(['error' => 'true', 'message' => 'Ano Invalido'], 400);
        }

        if ($request->value <=  0) {
            return response()->json(['error' => 'true', 'message' => 'Valor invalido'], 400);
        }

        if ($request->expected_value <=  0) {
            return response()->json(['error' => 'true', 'message' => 'Valor Geral invalido'], 400);
        }

        $goal = Goal::where('month', $request->month)->where('year', $request->year)->first();
        if ($goal) {
            return response()->json(['error' => 'true', 'message' => 'Meta ja cadastrada para este periodo'], 400);
        }

        $newGoal = new Goal();
        $newGoal->month = $request->month;
        $newGoal->year = $request->year;
        $newGoal->value = $request->value;
        $newGoal->expected_value = $request->expected_value;
        $newGoal->save();

        return response()->json(['error' => 'false', 'message' => 'Meta cadastrada com sucesso']);
    }

    public function updateGoal(Request $request)
    {
        if (!isset($request->id)) {
            return response()->json(['error' => 'true', 'message' => 'Id não informado'], 400);
        }

        if (!isset($request->value) && !isset($request->expected_value)) {
            return response()->json(['error' => 'true', 'message' => 'Valor não informado'], 400);
        }

        if (isset($request->value) && $request->value <=  0) {
            return response()->json(['error' => 'true', 'message' => 'Valor invalido'], 400);
        }

        if (isset($request->expected_value) && $request->expected_value <=  0) {
            return response()->json(['error' => 'true', 'message' => 'Valor invalido'], 400);
        }


        $goal = Goal::where('id', $request->id)->first();

        if (!$goal) {
            return response()->json(['error' => 'true', 'message' => 'Meta ' . $request->id . ' não encontrada'], 400);
        }

        if (isset($request->value)) {

            if ($request->value) {
                $goal->value = $request->value;
            }
        }

        if (isset($request->expected_value)) {
            if ($request->expected_value) {
                $goal->expected_value = $request->expected_value;
            }
        }


        $goal->save();

        return response()->json(['error' => 'false', 'message' => 'Meta atualizada com sucesso']);
    }

    public function selectGoal(Request $request, int $id = null)
    {
        if (!isset($id)) {
            $goal = Goal::get();
            if (!$goal) {
                return response()->json(['error' => 'true', 'message' => 'Meta ' . $id . ' nao encontrada'], 400);
            }

            return $goal;
        } else {
            $goal = Goal::where('id', $id)->first();

            if (!$goal) {
                return response()->json(['error' => 'true', 'message' => 'Meta ' . $id . ' nao encontrada'], 400);
            }
            return $goal;
        }
    }

    public function calendarGoals(Request $request,  $date_init,  $date_end)
    {
        $response = [];

        for ($i = 0; $i < Carbon::parse($date_end)->diffInDays(Carbon::parse($date_init)) + 1; $i++) {

            $dtInicio = Carbon::parse($date_init);
            $dtFim = Carbon::parse($date_init)->addDay($i);

            $aprovadosMes = $this->reportsService->GetApproveds(["date_init" => $dtInicio, "date_end" => $dtFim])->count;
            $aprovadosAno = $this->reportsService->GetApproveds(["date_init" => Carbon::now()->startOfYear(), "date_end" => $dtFim])->count;

            $monthGoal =  $this->reportsService->GetGoalByMountAndYear(intval($dtFim->format('m')), intval($dtFim->format('Y')));
            $yearGoals =  $this->reportsService->GetGoalYear(intval($dtFim->format('Y')));

            $CurrentMonthValue = $this->reportsService->GetApproveds(['date_init' => $dtInicio->format('Y-m-d'), 'date_end' => $dtFim->format('Y-m-d')]);
            $CurrentYearValue = $this->reportsService->GetApproveds(['date_init' => Carbon::now()->startOfYear(), 'date_end' => $dtFim->format('Y-m-d')]);

            try {
                $goals = [
                    "date" => $dtFim->format('Y-m-d'),
                    "mes" => [
                        "porcentagemReais" => (($CurrentMonthValue->sum * 100) / $monthGoal->value),
                        "atualReais" => $CurrentMonthValue->sum == null ? 0 : $CurrentMonthValue->sum,
                        "metaReais" =>  $monthGoal->value,

                        "porcentagemJobs" => ($aprovadosMes * 100) / $monthGoal->expected_value,
                        "atualJobs" => $aprovadosMes,
                        "metaJobs" => $monthGoal->expected_value,
                    ],
                    "anual" => [
                        "porcentagemReais" => (($CurrentYearValue->sum * 100) / $yearGoals->value),
                        "atualReais" =>  $CurrentYearValue->sum == null ? 0 : $CurrentYearValue->sum,
                        "metaReais" =>  $yearGoals->value,

                        "porcentagemJobs" => ($aprovadosAno * 100) / $yearGoals->expected_value,
                        "atualJobs" => $aprovadosAno,
                        "metaJobs" => $yearGoals->expected_value,
                    ]
                ];
            } catch (Exception $e) {
                return ([$yearGoals, $i]);
            }

            array_push($response, $goals);
        }

        return $response;
    }
}
