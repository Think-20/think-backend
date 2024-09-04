<?php

namespace App\Http\Controllers;

use App\Person;
use Illuminate\Http\Request;


class PersonController extends Controller
{
    public function __construct() {}

    public function selectPerson(Request $request, int $id = null)
    {
        if (!isset($id)) {
            $payment = Person::list();
            if (!$payment) {
                return response()->json(['error' => 'true', 'message' => 'Nenhuma pessoa encontrado'], 400);
            }

            return $payment;
        } else {
            $payment = Person::getUnique($id);
            
            /*if (!$payment) {
                return response()->json(['error' => 'true', 'message' => 'Pessoa de id' . $id . ' nao encontrada'], 400);
            }*/

            return $payment;
        }
    }

    public function createPerson(Request $request)
    {
        $payment = Person::create($request->all());
        return response()->json(['error' => 'false', 'message' => 'Pessoa cadastrada com sucesso', 'object' => $payment]);
    }

    public function updatePerson(Request $request)
    {
        $payment = Person::find($request->id);
        $payment->update($request->all());

        return response()->json(['error' => 'false', 'message' => 'Pessoa atualizada com sucesso', 'object' => $payment]);
    }
}
