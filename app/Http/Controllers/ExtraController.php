<?php

namespace App\Http\Controllers;

use App\Checkin;
use App\Extra;
use App\ExtraItem;
use App\Job;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExtraController extends Controller
{
    public function __construct() {}

    public function selectExtra(Request $request, int $id = null)
    {
        if ($id == null) {
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

    public function selectExtraItem(Request $request, int $id = null)
    {
        if ($id != null) {
            $extra = ExtraItem::list();
            if (!$extra) {
                return response()->json(['error' => 'true', 'message' => 'Nenhum extra encontrado'], 400);
            }

            return $extra;
        } else {
            $extra = ExtraItem::getUnique($id);

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
        $job = Job::where("id", $request->job_id)->first();
        if ($job == false) {
            return response()->json(['error' => 'true', 'message' => 'Id de job invalido.'], 400);
        }

        $extra = Extra::create($request->all());
        if ($extra == false) {
            return response()->json(['error' => 'true', 'message' => 'Erro ao criar o extra.'], 400);
        }

        //devolve o status de extra aceito no checkin para false sempre que o cliente mudar algo nos extras
        $checkin = Checkin::where('job_id', '=',  $request->job_id)->first();

        $checkin->update([
            'extras_accept_client' => 0,
            'extras_accept_client_date' => Carbon::now()
        ]);

        return response()->json(['error' => 'false', 'message' => 'Extra cadastrada com sucesso', 'object' => $extra]);
    }

    public function createExtraItem(Request $request)
    {
        $extraItem = ExtraItem::create($request->all());
        if ($extraItem == false) {
            return response()->json(['error' => 'true', 'message' => 'Erro ao criar o extra.'], 400);
        }

        $extra = Extra::where("id",$extraItem->extra_id)->first();

        //devolve o status de extra aceito no checkin para false sempre que o cliente mudar algo nos extras
        $checkin = Checkin::where('job_id', '=',  $extra->job_id)->latest('id')->first();

        $checkin->update([
            'extras_accept_client' => 0,
            'extras_accept_client_date' => Carbon::now()
        ]);

        $extraItem->requester_object;
        $extraItem->budget_object;        
        $extraItem->billing_employee_object;

        return response()->json(['error' => 'false', 'message' => 'Extra cadastrada com sucesso', 'object' => $extraItem]);
    }

    public function updateExtra(Request $request)
    {
        $extra = Extra::find($request->id);
        if ($extra == false) {
            return response()->json(['error' => 'true', 'message' => 'Erro ao encontrar o extra.'], 400);
        }

        $job = Job::where("id", $extra->job_id)->first();
        if ($job == false) {
            return response()->json(['error' => 'true', 'message' => 'Não foi encontrado nenhum job relacionado a esse extra.'], 400);
        }

        $extra->update($request->all());

        //devolve o status de extra aceito no checkin para false sempre que o cliente mudar algo nos extras
        $checkin = Checkin::where('job_id', '=',  $extra->job_id)->first();
        $checkin->update([
            'extras_accept_client' => 0,
            'extras_accept_client_date' => Carbon::now()
        ]);

        return response()->json(['error' => 'false', 'message' => 'Extra atualizada com sucesso', 'object' => $extra]);
    }

    public function updateExtraItem(Request $request)
    {
        $extraItem = ExtraItem::find($request->id);
        if ($extraItem == false) {
            return response()->json(['error' => 'true', 'message' => 'Erro ao encontrar o extra_item.'], 400);
        }

        $extra = Extra::where("id",$extraItem->extra_id)->first();

        $job = Job::where("id", $extra->job_id)->first();
        if ($job == false) {
            return response()->json(['error' => 'true', 'message' => 'Não foi encontrado nenhum job relacionado a esse extra.'], 400);
        }

        $extraItem->update($request->all());

        $extraItem->requester_object;
        $extraItem->budget_object;        
        $extraItem->billing_employee_object;

        //devolve o status de extra aceito no checkin para false sempre que o cliente mudar algo nos extras
        $checkin = Checkin::where('job_id', '=',  $extra->job_id)->first();
        $checkin->update([
            'extras_accept_client' => 0,
            'extras_accept_client_date' => Carbon::now()
        ]);

        return response()->json(['error' => 'false', 'message' => 'Extra atualizada com sucesso', 'object' => $extraItem]);
    }

    public function deleteExtra(Request $request, int $id = null)
    {
        if (!isset($id)) {
            return response()->json(['error' => 'true', 'message' => 'Não foi recebido o Id'], 400);
        } else {
            $extra = Extra::where("id", $id)->first();
            if (!$extra) {
                return response()->json(['error' => 'true', 'message' => 'Extra de id ' . $id . ' nao encontrada'], 400);
            }

            $job = Job::where("id", $extra->job_id)->first();
            if ($job == false) {
                return response()->json(['error' => 'true', 'message' => 'Não foi encontrado nenhum job relacionado a esse extra.'], 400);
            }

            //devolve o status de extra aceito no checkin para false sempre que o cliente mudar algo nos extras
            $checkin = Checkin::where('job_id', '=',  $extra->job_id)->first();
            $checkin->update([
                'extras_accept_client' => 0,
                'extras_accept_client_date' => Carbon::now()
            ]);

            $extra->delete();

            return response()->json(['error' => 'false', 'message' => 'Extra de Id ' . $id . ' deletado com sucesso'], 200);
        }
    }

    public function deleteExtraItem(Request $request, int $id = null)
    {
        if (!isset($id)) {
            return response()->json(['error' => 'true', 'message' => 'Não foi recebido o Id'], 400);
        } else {
            $extraItem = ExtraItem::where("id", $id)->first();
            $extra = Extra::where("id",$extraItem->extra_id)->first();
            if (!$extraItem) {
                return response()->json(['error' => 'true', 'message' => 'Extra_item de id ' . $id . ' nao encontrada'], 400);
            }

            $job = Job::where("id", $extra->job_id)->first();
            if ($job == false) {
                return response()->json(['error' => 'true', 'message' => 'Não foi encontrado nenhum job relacionado a esse extra.'], 400);
            }

            //devolve o status de extra aceito no checkin para false sempre que o cliente mudar algo nos extras
            $checkin = Checkin::where('job_id', '=',  $extra->job_id)->first();
            $checkin->update([
                'extras_accept_client' => 0,
                'extras_accept_client_date' => Carbon::now()
            ]);

            $extraItem->delete();

            return response()->json(['error' => 'false', 'message' => 'Extra de Id ' . $id . ' deletado com sucesso'], 200);
        }
    }
}
