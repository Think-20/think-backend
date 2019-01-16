<?php

namespace App\Http\Controllers;

use App\Employee;
use Illuminate\Http\Request;
use Response;
use Exception;
use DB;

class EmployeeController extends Controller
{
    public static function all() {
        return Employee::list();
    }

    public static function filter(Request $request) {
        return Employee::filter($request->all());
    }

    public static function get(int $id) {
        return Employee::get($id);
    }

    public static function canInsertClients() {
        return Employee::canInsertClients();
    }

    public static function save(Request $request) {
        $status = false;

        try {
            $employee = Employee::insert($request->all());
            $message = 'Funcionário cadastrado com sucesso!';
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
            Employee::edit($request->all());
            $message = 'Funcionário alterado com sucesso!';
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
            $employee = Employee::remove($id);
            $message = 'Funcionário deletado com sucesso!';
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
