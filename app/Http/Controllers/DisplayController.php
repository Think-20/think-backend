<?php

namespace App\Http\Controllers;

use App\Display;
use Illuminate\Http\Request;
use Response;
use Exception;
use DB;

class DisplayController extends Controller
{
    public static function all() {
        return Display::list();
    }

    public static function filter(Request $request) {
        return Display::filter($request->all());
    }

    public static function get(int $id) {
        return Display::get($id);
    }

    public static function save(Request $request) {
        $status = false;

        try {
            $display = Display::insert($request->all());
            $message = 'Acesso cadastrado com sucesso!';
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
            Display::edit($request->all());
            $message = 'Acesso alterado com sucesso!';
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
            $display = Display::remove($id);
            $message = 'Acesso deletado com sucesso!';
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
