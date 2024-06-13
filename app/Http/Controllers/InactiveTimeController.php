<?php

namespace App\Http\Controllers;

use App\InactiveTime;
use Illuminate\Http\Request;


class InactiveTimeController extends Controller
{
    public function updateInactiveTime(Request $request)
    {

        if (!isset($request->type)) {
            return response()->json(['error' => 'true', 'message' => 'E necessario informar o tipo que deseja alterar.'], 400);
        }

        if (isset($request->notification_time) && $request->notification_time <=  0) {
            return response()->json(['error' => 'true', 'message' => 'Quantidade invalida para tempo de notificacao.'], 400);
        }

        if (isset($request->inactive_time) && $request->inactive_time <=  0) {
            return response()->json(['error' => 'true', 'message' => 'Quantidade invalida para tempo de invalidacao.'], 400);
        }

        $inactiveTime = InactiveTime::where('type', $request->type)->first();


        if (!$inactiveTime) {
            return response()->json(['error' => 'true', 'message' => 'Não foi possivel encontrar a configuração para o tipo ' . $request->type], 400);
        }

        if (isset($request->notification_time)) {
            if ($request->notification_time) {
                $inactiveTime->notification_time = $request->notification_time;
            }
        }

        if (isset($request->inactive_time)) {
            if ($request->inactive_time) {
                $inactiveTime->inactive_time = $request->inactive_time;
            }
        }

        $inactiveTime->save();

        return response()->json(['error' => 'false', 'message' => 'Tempo de inativacao atualizado com sucesso']);
    }

    public function selectInactiveTime(Request $request, int $id = null)
    {
        /*if (!isset($id)) {
            $inactiveTime = InactiveTime::get();
            if (!$inactiveTime) {
                return response()->json(['error' => 'true', 'message' => 'Meta ' . $id . ' nao encontrada'], 400);
            }

            return $inactiveTime;
        } else {
            $inactiveTime = InactiveTime::where('id', $id)->first();

            if (!$inactiveTime) {
                return response()->json(['error' => 'true', 'message' => 'Meta ' . $id . ' nao encontrada'], 400);
            }
            return $inactiveTime;
        }*/

        return InactiveTime::get();
    }
}
