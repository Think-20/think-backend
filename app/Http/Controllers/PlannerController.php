<?php

namespace App\Http\Controllers;

use App\Planner;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PlannerController extends Controller
{
    public function __construct() {}

    public function selectPlanner(Request $request, int $id = null)
    {
        if (!isset($id)) {
            $planner = Planner::list();
            if (!$planner) {
                return response()->json(['error' => 'true', 'message' => 'Nenhum planejamento encontrado'], 400);
            }

            return $planner;
        } else {
            $planner = Planner::getUnique($id);

            if (!$planner) {
                return response()->json(['error' => 'true', 'message' => 'planejamento ' . $id . ' nao encontrado'], 400);
            }
            return $planner;
        }
    }

    public function createPlanner(Request $request)
    {
        $request['employee_id'] = User::logged()->employee->id;
        
        $planner = Planner::create($request->all());
        return response()->json(['error' => 'false', 'message' => 'Planejamento cadastrada com sucesso', 'object' => $planner]);
    }

    public function updatePlanner(Request $request)
    {
        $planner = Planner::find($request->id);
        $planner->update($request->all());

        return response()->json(['error' => 'false', 'message' => 'Planejamento atualizado com sucesso', 'object' => $planner]);
    }
}

