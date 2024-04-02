<?php

namespace App\Http\Controllers;

use App\Employee;
use App\JobActivity;
use Illuminate\Http\Request;
use Response;
use Exception;
use DB;

class EmployeeController extends Controller
{
    public static function all()
    {
        return Employee::list();
    }

    public static function filter(Request $request)
    {
        return Employee::filter($request->all());
    }

    public static function get(int $id)
    {
        return Employee::get($id);
    }

    public static function myGet(int $id)
    {
        return Employee::myGet($id);
    }

    public static function canInsertClients(Request $request)
    {
        return Employee::canInsertClients($request->all());
    }

    public static function save(Request $request)
    {
        $status = false;

        try {
            $employee = Employee::insert($request->all());

            if ($employee['department_id'] == 5 && $employee['position_id'] == 7) {
                DB::insert('INSERT INTO job_activity_employee(job_activity_id, employee_id) VALUES (?, ?),(?,?),(?, ?),(?,?),(?, ?),(?,?),(?, ?)', [
                    1, $employee['id'],
                    8, $employee['id'],
                    9, $employee['id'],
                    11, $employee['id'],
                    17, $employee['id'],
                    18, $employee['id'],
                    20, $employee['id']
                ]);
            } else {
                DB::insert('INSERT INTO job_activity_employee(job_activity_id, employee_id) VALUES (?, ?)', [
                    13, $employee['id']
                ]);
            }



            $message = 'Funcionário cadastrado com sucesso!';
            $status = true;
        } catch (Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao cadastrar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }

    public static function edit(Request $request)
    {
        $status = false;

        try {
            Employee::edit($request->all());
            $message = 'Funcionário alterado com sucesso!';
            $status = true;
        } catch (Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao atualizar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }

    public static function myEdit(Request $request)
    {
        $status = false;

        try {
            Employee::myEdit($request->all());
            $message = 'Funcionário alterado com sucesso!';
            $status = true;
        } catch (Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao atualizar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }

    public static function toggleDeleted(int $id)
    {
        $status = false;

        try {
            $employee = Employee::toggleDeleted($id);
            $message = 'Funcionário alterado com sucesso!';
            $status = true;
        } catch (QueryException $queryException) {
            $message = 'Um erro ocorreu ao alterar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao alterar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }
}
