<?php

namespace App\Http\Controllers;

use App\Budget;
use Exception;
use Response;

use DB;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\FileHelper;

class BudgetController extends Controller
{
    public static function save(Request $request)
    {
        $data = $request->all();
        $status = false;
        $budget = null;

        DB::beginTransaction();

        try {
            $budget = Budget::insert($data);
            $message = 'Orçamento cadastrado com sucesso!';
            DB::commit();
            $status = true;
        }
        /* Catch com FileException tamanho máximo */ catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
            //. $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'budget' => $budget
        ]), 200);
    }

    public static function edit(Request $request)
    {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();
        //$oldBudget = Budget::find($request->id);
        //$oldChild = Budget::getBudgetChild($oldBudget);

        try {
            $budget = Budget::edit($data);
            $message = 'Orçamento alterado com sucesso!';
            $status = true;
            DB::commit();
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'budget' => $budget
        ]), 200);
    }

    public static function remove(int $id)
    {
        DB::beginTransaction();
        $status = false;

        try {
            $budget = Budget::remove($id);
            $message = 'Orçamento removido com sucesso!';
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

    public static function get(int $id)
    {

        

        $budget = [
            "date" => 0,
            "preOrcamento" => [
                "atendimento" => 0,
                "cliente" => 0,
                "evento" =>  0,
                "local" => 0,
                "orcamentista" => 0,
                "gerente" => 0,
                "detalhamento" => 0,
                "produtor" => 0,
            ],
            "informacoesDoEvento" => [
                "dataDoEvento" => 0,
                "verba" => 0,
                "areaTerreno" =>  0,
                "mezanino" => 0,
                "dataMontagem" => 0,
                "dataInicioEvento" => 0,
                "dataFimEvento" => 0,
                "dataDesmontagem" => 0,
            ]
        ];

        return $budget;
    }
}
