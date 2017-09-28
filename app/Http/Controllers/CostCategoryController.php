<?php

namespace App\Http\Controllers;

use App\CostCategory;
use Exception;
use Response;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class CostCategoryController extends Controller
{
    public static function save(Request $request) {
        $status = false;

        try {
            $costCategory = CostCategory::insert($request->all());
            $message = 'Categoria de custo cadastrada com sucesso!';
            $status = true;
        } catch(QueryException $queryException) {
            if($queryException->getCode() == 23000) {
                $message = 'Já existe uma categoria idêntica cadastrada.';
            } else {
                $message = 'Um erro ocorreu ao cadastrar no banco de dados.';
            }
        } catch(Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao cadastrar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function edit(Request $request) {
        $status = false;

        try {
            $costCategory = CostCategory::edit($request->all());
            $message = 'Categoria de custo alterada com sucesso!';
            $status = true;
        } catch(QueryException $queryException) {
            if($queryException->getCode() == 23000) {
                $message = 'Já existe uma categoria como essa cadastrada.';
            } else {
                $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
            }
        } catch(Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao atualizar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function remove(int $id) {
        $status = false;

        try {
            $costCategory = CostCategory::remove($id);
            $message = 'Categoria de custo deletada com sucesso!';
            $status = true;
        } catch(QueryException $queryException) {
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao deletar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function get(int $id) {
        return CostCategory::get($id);
    }

    public static function all() {
        return CostCategory::list();
    }

    public static function filter($query) {
        return CostCategory::filter($query);
    }
}
