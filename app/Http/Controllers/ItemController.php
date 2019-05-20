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
        $item = null;

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
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'item' => $item
         ]), 200);
    }

    public static function savePricing(int $id, Request $request) {
        $status = false;
        $pricing = null;

        try {
            $item = Item::find($id);
            $pricing = $item->addPricing($request->all());
            $message = 'Preço adicionado com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao adicionar o preço: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'pricing' => $pricing
         ]), 200);
    }

    public static function removePricing(int $itemId, int $pricingId) {
        $status = false;

        try {
            $item = Item::find($itemId);
            $item->removePricing($pricingId);
            $message = 'Preço removido com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao remover o preço: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status
         ]), 200);
    }

    public static function saveChildItem(int $id, Request $request) {
        $status = false;
        $childItem = null;

        try {
            $item = Item::find($id);
            $childItem = $item->addChildItem($request->all());
            $message = 'Item filho adicionado com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao adicionar o item filho: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'childItem' => $childItem
         ]), 200);
    }

    public static function removeChildItem(int $itemId, int $childItemId) {
        $status = false;

        try {
            $item = Item::find($itemId);
            $item->removeChildItem($childItemId);
            $message = 'Item filho removido com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao remover o item filho: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status
         ]), 200);
    }

    public static function image(Request $request) {
        $file = $request->file('image');
        $name = md5(time()) . $file->getClientOriginalExtension();
        $file->move(resource_path('assets/images'), $name);

        return ['name' => $name];
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
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
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

    public static function all(Request $request) {
        return Item::list($request->all());
    }

    public static function filter(Request $request) {
        return Item::filter($request->all());
    }
}
