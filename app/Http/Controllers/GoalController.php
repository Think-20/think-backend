<?php

namespace App\Http\Controllers;

use App\Goal;
use Illuminate\Http\Request;

class GoalController extends Controller
{

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

        $goal = Goal::where('month', $request->month)->where('year', $request->year)->first();
        if ($goal) {
            return response()->json(['error' => 'true', 'message' => 'Meta ja cadastrada para este periodo'], 400);
        }

        $newGoal = new Goal();
        $newGoal->month = $request->month;
        $newGoal->year = $request->year;
        $newGoal->value = $request->value;
        $newGoal->save();

        return response()->json(['error' => 'false', 'message' => 'Meta cadastrada com sucesso']);
    }

    public function updateGoal(Request $request)
    {
        if (!isset($request->id)) {
            return response()->json(['error' => 'true', 'message' => 'Id não informado'], 400);
        }

        if (!isset($request->value)) {
            return response()->json(['error' => 'true', 'message' => 'Valor não informado'], 400);
        }

        if ($request->value <=  0) {
            return response()->json(['error' => 'true', 'message' => 'Valor invalido'], 400);
        }

        $goal = Goal::where('id', $request->id)->first();

        if (!$goal) {
            return response()->json(['error' => 'true', 'message' => 'Meta ' . $request->id . ' não encontrada'], 400);
        }

        if (isset($request->value)) {
            $goal->value = $request->value;
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

            //return response()->json(['error' => 'true', 'message' => 'Id não informado'], 400);
        } else {
            $goal = Goal::where('id', $id)->first();

            if (!$goal) {
                return response()->json(['error' => 'true', 'message' => 'Meta ' . $id . ' nao encontrada'], 400);
            }

            return $goal;
        }
    }
}
