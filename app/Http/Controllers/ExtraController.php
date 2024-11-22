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
                return response()->json(['error' => 'true', 'message' => 'Extra de id' . $id . ' nao encontrada'], 400);
            }

            return $extra;
        }
    }

    public function selectExtraHash(Request $request, int $id = null, string $hash = null)
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
                return response()->json(['error' => 'true', 'message' => 'Extra de id' . $id . ' nao encontrada'], 400);
            }

            return $extra;
        }
    }
 
    public function createExtra(Request $request)
    {
        $extra = Extra::create($request->all());
        return response()->json(['error' => 'false', 'message' => 'Extra cadastrada com sucesso', 'object' => $extra]);
    }

    public function updateExtra(Request $request)
    {
        $extra = Extra::find($request->id);
        
        $extra->update($request->all());

        return response()->json(['error' => 'false', 'message' => 'Extra atualizada com sucesso', 'object' => $extra]);
    }

    public function deleteExtra(Request $request, int $id = null)
    {
        if (!isset($id)) {
            return response()->json(['error' => 'true', 'message' => 'Extra de Id ' . $id . ' nao encontrado'], 400);
        } else {
            $extra = Extra::find($request->id);

            if (!$extra) {
                return response()->json(['error' => 'true', 'message' => 'Extra de id ' . $id . ' nao encontrada'], 400);
            }

            $extra->delete();

            return response()->json(['error' => 'false', 'message' => 'Extra de Id ' . $id . ' deletado com sucesso'], 200);
        }
    }

    /*public function deleteExtra(Request $request, int $id = null)
    {
        $payment = Extra::find($request->id);
        $payment->delete();

        return response()->json(['error' => 'false', 'message' => 'Extra atualizada com sucesso', 'object' => $payment]);
    }*/
}
