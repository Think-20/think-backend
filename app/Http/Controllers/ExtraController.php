<?php

namespace App\Http\Controllers;

use App\Extra;
use Illuminate\Http\Request;

class ExtraController extends Controller
{
    public function __construct() {}

    public function selectExtra(Request $request, int $id = null)
    {
        if (!isset($id)) {
            $extra = Extra::list();
            if (!$extra) {
                return response()->json(['error' => 'true', 'message' => 'Nenhum extra encontrado'], 400);
            }

            return $extra;
        } else {
            $extra = Extra::getUnique($id);

            if (!$extra) {
                return response()->json(['error' => 'true', 'message' => 'Pessoa de id' . $id . ' nao encontrada'], 400);
            }

            return $extra;
        }
    }

    public function createExtra(Request $request)
    {
        $payment = Extra::create($request->all());
        return response()->json(['error' => 'false', 'message' => 'Pessoa cadastrada com sucesso', 'object' => $payment]);
    }

    public function updateExtra(Request $request)
    {
        $payment = Extra::find($request->id);
        $payment->update($request->all());

        return response()->json(['error' => 'false', 'message' => 'Pessoa atualizada com sucesso', 'object' => $payment]);
    }
}
