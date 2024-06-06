<?php

namespace App\Http\Controllers;

use App\Functionality;
use Illuminate\Http\Request;
use Response;
use Exception;
use DB;

class FunctionalityController extends Controller
{
    public static function all(Request $request) {
        return Functionality::list($request->all());
    }

    public static function filter(Request $request) {
        return Functionality::filter($request->all());
    }

    public static function get(int $id) {
        return Functionality::get($id);
    }

    public static function save(Request $request) {
        $status = false;

        try {
            $functionality = Functionality::insert($request->all());
            $message = 'Rota cadastrada com sucesso!';
            $status = true;
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
            Functionality::edit($request->all());
            $message = 'Rota alterada com sucesso!';
            $status = true;
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
            $functionality = Functionality::remove($id);
            $message = 'Rota deletada com sucesso!';
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
}
