<?php

namespace App\Http\Controllers;

use App\ItemCategory;
use Exception;
use Response;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class ItemCategoryController extends Controller
{
    public static function save(Request $request) {
        $status = false;

        try {
            $itemCategory = ItemCategory::insert($request->all());
            $message = 'Categoria de item cadastrada com sucesso!';
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
            $itemCategory = ItemCategory::edit($request->all());
            $message = 'Categoria de item alterada com sucesso!';
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
            $itemCategory = ItemCategory::remove($id);
            $message = 'Categoria de item deletada com sucesso!';
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
        return ItemCategory::get($id);
    }

    public static function all() {
        $items = ItemCategory::list();

        foreach($items as $item) {
            $item->itemCategory;
        }

        return $items;
    }

    public static function filter($query) {
        return ItemCategory::filter($query);
    }
}
