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
<<<<<<< HEAD
    public static function save(Request $request)
    {
=======
    public static function save(Request $request) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        $data = $request->all();
        $status = false;
        $budget = null;

        DB::beginTransaction();

        try {
            $budget = Budget::insert($data);
            $message = 'Orçamento cadastrado com sucesso!';
            DB::commit();
            $status = true;
<<<<<<< HEAD
        }
        /* Catch com FileException tamanho máximo */ catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
            //. $e->getFile() . $e->getLine();
=======
        } 
        /* Catch com FileException tamanho máximo */
        catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
             //. $e->getFile() . $e->getLine();
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'budget' => $budget
<<<<<<< HEAD
        ]), 200);
    }

    public static function edit(Request $request)
    {
=======
         ]), 200);
    }

    public static function edit(Request $request) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
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
<<<<<<< HEAD
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
=======
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'budget' => $budget
<<<<<<< HEAD
        ]), 200);
    }

    public static function remove(int $id)
    {
=======
         ]), 200);
    }
    
    
    public static function remove(int $id) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        DB::beginTransaction();
        $status = false;

        try {
            $budget = Budget::remove($id);
            $message = 'Orçamento removido com sucesso!';
            $status = true;
            DB::commit();
<<<<<<< HEAD
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
=======
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
            DB::rollBack();
            $message = 'Um erro desconhecido ocorreu ao deletar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
<<<<<<< HEAD
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
=======
         ]), 200);
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
    }
}
