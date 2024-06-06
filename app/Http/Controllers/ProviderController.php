<?php

namespace App\Http\Controllers;

use App\Provider;
use Exception;
use Response;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class ProviderController extends Controller
{
    public static function save(Request $request) {
        $status = false;

        try {
            $provider = Provider::insert($request->all());
            $message = 'Fornecedor cadastrado com sucesso!';
            $status = true;
        } catch(QueryException $queryException) {
            if($queryException->getCode() == 23000) {
                $message = 'Erro de entrada ou já existe um cadastro como esse.' . $queryException->getMessage();
            } else {
                $message = 'Um erro ocorreu ao cadastrar no banco de dados.' . $queryException->getMessage();
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
            Provider::edit($request->all());
            $message = 'Fornecedor alterado com sucesso!';
            $status = true;
        } catch(QueryException $queryException) {
            if($queryException->getCode() == 23000) {
                $message = 'Erro de entrada ou já existe um cadastro como esse.' . $queryException->getMessage();
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
            $provider = Provider::remove($id);
            $message = 'Fornecedor deletado com sucesso!';
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
        return Provider::get($id);
    }

    public static function all() {
        return Provider::list();
    }

    public static function filter(Request $request) {
        return Provider::filter($request->all());
    }
}
