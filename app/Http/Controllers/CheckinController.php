<?php

namespace App\Http\Controllers;

use App\Checkin;
use App\Client;
use Illuminate\Http\Request;

class CheckinController extends Controller
{

    public function __construct() {}

    public function selectCheckin(Request $request, int $id = null)
    {
        if (!isset($id)) {
            $checkin = Checkin::get();
            if (!$checkin) {
                return response()->json(['error' => 'true', 'message' => 'Nenhum Checkin encontrado'], 400);
            }

            return $checkin;
        } else {
            $checkin = Checkin::where('id', $id)->first();

            if (!$checkin) {
                return response()->json(['error' => 'true', 'message' => 'Checkin ' . $id . ' nao encontrado'], 400);
            }
            return $checkin;
        }
    }

    public function createCheckin(Request $request)
    {
        /*if ($request->address_number < 0) {
            return response()->json(['error' => 'true', 'message' => 'Número invalido para o endereço'], 400);
        }

        $client = Client::where('id', $request->client_id)->first();

        if (!$client) {
            return response()->json(['error' => 'true', 'message' => 'Cliente não cadastrado.'], 400);
        }

        $organization = new Checkin();
        $organization->name = $request->name;
        $organization->city = $request->city;
        $organization->address = $request->address;
        $organization->address_number = $request->address_number;
        $organization->site = $request->site;
        $organization->client_id = $request->client_id;
        $organization->save();*/
        
        Checkin::create($request->all());
        return response()->json(['error' => 'false', 'message' => 'Checkin cadastrada com sucesso']);
    }

    public function updateCheckin(Request $request)
    {
        /*$organization = Organization::where('id', $request->id)->first();

        if (!$organization) {
            return response()->json(['error' => 'true', 'message' => 'Organização ' . $request->id . ' não encontrada'], 400);
        }

        if (isset($request->name)) {
            if ($request->name) {
                $organization->name = $request->name;
            }
        }

        if (isset($request->city)) {
            if ($request->city) {
                $organization->name = $request->city;
            }
        }

        if (isset($request->address)) {
            if ($request->address) {
                $organization->address = $request->address;
            }
        }

        if (isset($request->address_number)) {
            if ($request->address_number) {
                $organization->address_number = $request->address_number;
            }
        }

        if (isset($request->site)) {
            if ($request->site) {
                $organization->site = $request->site;
            }
        }

        if (isset($request->client_id)) {
            if ($request->client_id) {
                $organization->client_id = $request->client_id;
            }
        }

        $organization->save();*/

        #dd($request->all()[0]);

        $checkin = Checkin::find($request->id);
        $checkin->update($request->all());
        

        return response()->json(['error' => 'false', 'message' => 'Checkin atualizada com sucesso']);
    }
}
