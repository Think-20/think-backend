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
    public static function loadForm() {        
        return Response::make(json_encode([
            'data' => Budget::loadForm()
         ]), 200); 
    }
    

    public static function recalculateNextDate($nextEstimatedTime) {
        return Response::make(json_encode([
            'data' => Budget::recalculateNextDate($nextEstimatedTime)
         ]), 200); 
    }

    public static function save(Request $request) {
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
        /* Catch com FileException tamanho máximo */
        catch(Exception $e) {
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

    public static function edit(Request $request) {
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
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }
    
    public static function getNextAvailableDate($availableDate, $estimatedTime, $swap) {
        return Response::make(json_encode(Budget::getNextAvailableDate($availableDate, $estimatedTime, $swap)), 200); 
    }

    public static function editAvailableDate(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $budget = Budget::editAvailableDate($data);
            $message = 'Budget alterado com sucesso!';
            $status = true;
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }


    public static function myEditAvailableDate(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $budget = Budget::myEditAvailableDate($data);
            $message = 'Budget alterado com sucesso!';
            $status = true;
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    /*

    public static function recalculateNextDate($nextEstimatedTime) {
        return Response::make(json_encode([
            'data' => Budget::recalculateNextDate($nextEstimatedTime)
         ]), 200); 
    }

    public static function save(Request $request) {
        $data = $request->all();
        $status = false;
        $budget = null;

        DB::beginTransaction();

        try {
            $budget = Budget::insert($data);
            $code = str_pad($budget->code, 4, '0', STR_PAD_LEFT) . '/' . $budget->created_at->format('Y');
            $message = 'Budget ' . $code . ' cadastrado com sucesso!';
            DB::commit();
            $status = true;
        } 
        // Catch com FileException tamanho máximo
        catch(Exception $e) {
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

    public static function edit(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();
        //$oldBudget = Budget::find($request->id);
        //$oldChild = Budget::getBudgetChild($oldBudget);

        try {
            $budget = Budget::edit($data);
            $message = 'Budget alterado com sucesso!';
            $status = true;
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function downloadFile($id, $type, $file) {
        try {
            $file = Budget::downloadFile($id, $type, $file);
            $status = true;
            return Response::make(file_get_contents($file), 200, ['Content-Type' => mime_content_type($file)]);
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao abrir o arquivo: ' . $e->getMessage();
            return Response::make($message, 404);
        }
    }

    public static function remove(int $id) {
        DB::beginTransaction();
        $status = false;

        try {
            $budget = Budget::remove($id);
            $message = 'Budget deletado com sucesso!';
            $status = true;
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro desconhecido ocorreu ao deletar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function get(int $id) {
        return Budget::get($id);
    }

    public static function all() {
        $budgets = Budget::list();

        return $budgets;
    }

    public static function filter(Request $request) {
        return Budget::filter($request->all());
    }

    public static function saveMyBudget(Request $request) {
        $data = $request->all();
        $status = false;
        $budget = null;

        DB::beginTransaction();

        try {
            $budget = Budget::insert($data);
            $code = str_pad($budget->code, 4, '0', STR_PAD_LEFT) . '/' . $budget->created_at->format('Y');
            $message = 'Budget ' . $code . ' cadastrado com sucesso!';
            $status = true;
            DB::commit();
        } 
        //Catch com FileException tamanho máximo
        catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'budget' => $budget
         ]), 200);
    }

    public static function editMyBudget(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();
        //$oldBudget = Budget::find($request->id);
        //$oldChild = Budget::getBudgetChild($oldBudget);

        try {
            $budget = Budget::editMyBudget($data);
            $message = 'Budget alterado com sucesso!';
            $status = true;
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function downloadFileMyBudget($id, $type, $filename) {
        try {
            $file = Budget::downloadFileMyBudget($id, $type, $filename);
            $status = true;
            return Response::make(file_get_contents($file), 200, [
                'Content-Type' => mime_content_type($file), 
                'Content-Disposition: inline; filename="' . $filename . '"'
            ]);
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao abrir o arquivo: ' . $e->getMessage();
            return Response::make($message, 404);
        }
    }

    public static function removeMyBudget(int $id) {
        DB::beginTransaction();
        $status = false;

        try {
            $budget = Budget::removeMyBudget($id);
            $message = 'Budget deletado com sucesso!';
            $status = true;
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro desconhecido ocorreu ao deletar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function getMyBudget(int $id) {
        return Budget::getMyBudget($id);
    }

    public static function allMyBudget() {
        $budgets = Budget::listMyBudget();

        return $budgets;
    }

    public static function filterMyBudget($query) {
        return Budget::filterMyBudget($query);
    }
    */
}
