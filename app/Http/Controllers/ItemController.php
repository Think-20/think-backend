<?php

namespace App\Http\Controllers;

use App\Item;
use Exception;
use Response;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class ItemController extends Controller
{
    public static function save(Request $request) {
        $status = false;

        try {
            $item = Item::insert($request->all());
            $message = 'Item cadastrado com sucesso!';
            $status = true;
        } catch(QueryException $queryException) {
            if($queryException->getCode() == 23000) {
                $message = 'Já existe um item idêntico cadastrado.';
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

    public static function image(Request $request) {
        dd($request->all());
    }

    public static function edit(Request $request) {
        $status = false;

        try {
            $item = Item::edit($request->all());
            $message = 'Item alterado com sucesso!';
            $status = true;
        } catch(QueryException $queryException) {
            if($queryException->getCode() == 23000) {
                $message = 'Já existe um item como esse cadastrado.';
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
            $item = Item::remove($id);
            $message = 'Item deletado com sucesso!';
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
        return Item::get($id);
    }

    public static function all() {
        $items = Item::list();

        foreach($items as $item) {
            $item->item;
        }

        return $items;
    }

    public static function filter($query) {
        return Item::filter($query);
    }
}
