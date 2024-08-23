<?php

namespace App\Http\Controllers;

use App\Client;
use App\Organization;
use Illuminate\Http\Request;

class OrganizationCheckingController extends Controller
{
    public function __construct() {}

    public function selectOrganization(Request $request, int $id = null)
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

    public function createOrganization(Request $request)
    {
        //validations
        /*
        if ($request->month <=  0  || $request->month >= 13) {
            return response()->json(['error' => 'true', 'message' => 'Mes invalido'], 400);
        }

        if (strlen($request->year) !== 4) {
            return response()->json(['error' => 'true', 'message' => 'Ano Invalido'], 400);
        }

        if ($request->value <=  0) {
            return response()->json(['error' => 'true', 'message' => 'Valor invalido'], 400);
        }
        */

        if ($request->address_number < 0) {
            return response()->json(['error' => 'true', 'message' => 'Número invalido para o endereço'], 400);
        }

        $client = Client::where('id', $request->client_id)->first();
        
        if (!$client) {
            return response()->json(['error' => 'true', 'message' => 'Cliente não cadastrado.'], 400);
        }

        $organization = new Organization();
        $organization->name = $request->name;
        $organization->city = $request->city;
        $organization->address = $request->address;
        $organization->address_number = $request->address_number;
        $organization->site = $request->site;
        $organization->client_id = $request->client_id;
        $organization->save();

        return response()->json(['error' => 'false', 'message' => 'Organização cadastrada com sucesso']);
    }

    public function updateOrganization(Request $request)
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
}
