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
            $organization = Organization::list();
            if (!$organization) {
                return response()->json(['error' => 'true', 'message' => 'Organização ' . $id . ' nao encontrada'], 400);
            }

            return $organization;
        } else {
            #$organization = Organization::where('id', $id)->first();
            $organization = Organization::getUnique($id);

            if (!$organization) {
                return response()->json(['error' => 'true', 'message' => 'Organização ' . $id . ' nao encontrada'], 400);
            }
            return $organization;
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
        $respOrg = $organization->save();

        return response()->json(['error' => 'false', 'message' => 'Organização cadastrada com sucesso', "object" => $organization]);
    }

    public function updateOrganization(Request $request)
    {
        $organization = Organization::where('id', $request->id)->first();

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

        $organization->save();

        return response()->json(['error' => 'false', 'message' => 'Meta atualizada com sucesso', "object" => $organization]);
    }

    public static function removeOrganization(int $id)
    {
        
        DB::beginTransaction();
        $status = false;

        try {
            $organization = Organization::remove($id);
            $message = 'Organização deletada com sucesso!';
            $status = true;
            DB::commit();
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro desconhecido ocorreu ao deletar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }
}
