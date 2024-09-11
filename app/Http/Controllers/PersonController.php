<?php

namespace App\Http\Controllers;

use App\BankAccount;
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

            if (!$payment) {
                return response()->json(['error' => 'true', 'message' => 'Pessoa de id ' . $id . ' nao encontrada'], 400);
            }

            return $payment;
        }
    }

    public function createPerson(Request $request)
    {
        $bankAccount = new BankAccount();
        $bankAccount->favored = $request->name;
        $bankAccount->agency = $request->agency;
        $bankAccount->account_number = $request->account_number;
        $bankAccount->bank_account_type_id = 1;
        $bankAccount->bank_id = $request->bank_id;
        $bankAccount->save();

        $person = new Person();
        $person->bank_account_id = $bankAccount->id;
        $person->name = $request->name;
        $person->cpf = $request->cpf;
        $person->cnpj = $request->cnpj;
        $person->save();

        return response()->json(['error' => 'false', 'message' => 'Pessoa cadastrada com sucesso', 'object' => $person]);
    }

    public function updatePerson(Request $request)
    {

        $person = Person::find($request->id);

        $bankAccount = BankAccount::find($person->bank_account_id);
        $bankAccount->favored = $request->name;
        $bankAccount->agency = $request->agency;
        $bankAccount->account_number = $request->account_number;
        $bankAccount->bank_account_type_id = 1;
        $bankAccount->bank_id = $request->bank_id;
        $bankAccount->update();


        $person->bank_account_id = $bankAccount->id;
        $person->name = $request->name;
        $person->cpf = $request->cpf;
        $person->cnpj = $request->cnpj;
        $person->update();

        return response()->json(['error' => 'false', 'message' => 'Pessoa atualizada com sucesso', 'object' => $person]);
    }
}
