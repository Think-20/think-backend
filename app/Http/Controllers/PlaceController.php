<?php

namespace App\Http\Controllers;

use App\Place;
use Illuminate\Http\Request;
use Response;
use Exception;
use DB;

class PlaceController extends Controller
{
    public static function all(Request $request) {
        return Place::list($request->all());
    }

    public static function filter(Request $request) {
        return Place::filter($request->all());
    }

    public static function get(int $id) {
        return Place::get($id);
    }

    public static function save(Request $request) {
        $status = false;

        try {
            $place = Place::insert($request->all());
            $message = 'Local cadastrado com sucesso!';
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
            Place::edit($request->all());
            $message = 'Local alterado com sucesso!';
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
            $place = Place::remove($id);
            $message = 'Local deletado com sucesso!';
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
