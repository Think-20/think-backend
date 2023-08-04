<?php

namespace App\Http\Controllers;

use App\Client;
use Exception;
use Response;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class ClientController extends Controller
{
    public static function save(Request $request) {
        $status = false;

        try {
            $client = Client::insert($request->all());
            $message = 'Cliente cadastrado com sucesso!';
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
            Client::edit($request->all());
            $message = 'Cliente alterado com sucesso!';
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
            $client = Client::remove($id);
            $message = 'Cliente deletado com sucesso!';
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
        return Client::get($id);
    }

    public static function all() {
        return Client::list();
    }

    public static function filter(Request $request) {
        return Client::filter($request->all());
    }

    public static function import(Request $request) {
        $status = false;
        $informations = [];

        try {
            $informations = Client::import($request->file('file'));
            $message = 'Clientes importados com sucesso!';
            $status = true;
        } catch(QueryException $queryException) {
            $message = 'Um erro ocorreu ao importar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao importar: ' . $e->getMessage() . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'informations' => $informations
         ]), 200);
    }

    public static function saveMyClient(Request $request) {
        $status = false;

        try {
            $client = Client::insert($request->all());
            $message = 'Cliente cadastrado com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao cadastrar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function editMyClient(Request $request) {
        $status = false;

        try {
            $client = Client::editMyClient($request->all());
            $message = 'Cliente alterado com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao atualizar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function removeMyClient(int $id) {
        $status = false;

        try {
            $client = Client::removeMyClient($id);
            $message = 'Cliente deletado com sucesso!';
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

    public static function getMyClient(int $id) {
        return Client::getMyClient($id);
    }

    /*
    public static function allMyClient() {
        return Client::listMyClient();
    }

    public static function filterMyClient($query) {
        return Client::filterMyClient($query);
    }
    */
}
