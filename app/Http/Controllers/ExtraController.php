<?php

namespace App\Http\Controllers;

use App\Checkin;
use App\Extra;
use Carbon\Carbon;
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
        $extra = Extra::getByHash($id, $hash);

        if ($extra == false) {
            return response()->json(['error' => 'true', 'message' => 'Id ou Hash invalido.'], 400);
        }

        if (!$extra) {
            return response()->json(['error' => 'true', 'message' => 'Extra de id' . $id . ' nao encontrada'], 400);
        }

        return $extra;
    }

    public function createExtra(Request $request)
    {
        $extra = Extra::create($request->all());

        //devolve o status de extra aceito no checkin para false sempre que o cliente mudar algo nos extras
        $checkin = Checkin::where('id', '=', $extra->checkin_id)->first();
        $checkin->update([
            'extras_accept_client' => 0,
            'extras_accept_client_date' => Carbon::now()
        ]);

        return response()->json(['error' => 'false', 'message' => 'Extra cadastrada com sucesso', 'object' => $extra]);
    }

    public function updateExtra(Request $request)
    {

        $extra = Extra::find($request->id);
        $extra->update($request->all());

        //devolve o status de extra aceito no checkin para false sempre que o cliente mudar algo nos extras
        $checkin = Checkin::where('id', '=', $extra->checkin_id)->first();
        $checkin->update([
            'extras_accept_client' => 0,
            'extras_accept_client_date' => Carbon::now()
        ]);

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

            //devolve o status de extra aceito no checkin para false sempre que o cliente mudar algo nos extras
            $checkin = Checkin::where('id', '=', $extra->checkin_id)->first();
            $checkin->update([
                'extras_accept_client' => 0,
                'extras_accept_client_date' => Carbon::now()
            ]);

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
