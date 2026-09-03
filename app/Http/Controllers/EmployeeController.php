<?php

namespace App\Http\Controllers;

use App\Employee;
use App\CedenteRole;
use App\Http\Services\CedentePermissionService;
use Illuminate\Http\Request;
use Response;
use Exception;
use DB;
use InvalidArgumentException;

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
                //Criação
                DB::insert('INSERT INTO job_activity_employee(job_activity_id, employee_id) VALUES (?, ?),(?,?),(?, ?),(?,?),(?, ?),(?,?),(?, ?)', [
                    1, $employee['id'],
                    8, $employee['id'],
                    9, $employee['id'],
                    11, $employee['id'],
                    17, $employee['id'],
                    18, $employee['id'],
                    20, $employee['id']
                ]);
            }else if ($employee['department_id'] == 7 && $employee['position_id'] == 6) { 
                //Orçamento
                DB::insert('INSERT INTO job_activity_employee(job_activity_id, employee_id) VALUES (?, ?),(?,?),(?, ?),(?,?),(?, ?),(?,?),(?, ?)', [
                    2, $employee['id'],
                    14, $employee['id'],
                    15, $employee['id'],
                    16, $employee['id'],
                    21, $employee['id'],
                    19, $employee['id'],
                    13, $employee['id']
                ]);
            }else {
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

    /**
     * Lista papeis de cedente (id/code/name) para o formulario de cadastro.
     * Somente administrador (cedente_role.id = 3).
     */
    public static function cedenteRoles()
    {
        try {
            CedentePermissionService::assertCanRegisterEmployee();
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 403);
        }

        $roles = CedenteRole::orderBy('id', 'asc')->get()->map(function (CedenteRole $role) {
            return $role->toApiArray();
        })->values();

        return response()->json([
            'error' => 'false',
            'data' => $roles,
        ]);
    }

    /**
     * Cria employee + user do modulo de cedentes, atrelando fundo(s) e funcao por id.
     * Somente administrador (cedente_role.id = 3).
     */
    public static function saveCedente(Request $request)
    {
        try {
            CedentePermissionService::assertCanRegisterEmployee();
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 403);
        }

        try {
            $employee = Employee::insertForCedente($request->all());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'true',
                'message' => 'Um erro desconhecido ocorreu ao cadastrar: ' . $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'error' => 'false',
            'message' => 'Funcionario cadastrado com sucesso',
            'data' => $employee->toCedenteModuleArray(),
        ]);
    }

    /**
     * Altera employee do modulo de cedentes (nome, email, senha, funcao, fundos).
     * Somente administrador (cedente_role.id = 3).
     */
    public static function editCedente(Request $request)
    {
        try {
            CedentePermissionService::assertCanRegisterEmployee();
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 403);
        }

        try {
            $employee = Employee::editForCedente($request->all());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'true', 'message' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'true',
                'message' => 'Um erro desconhecido ocorreu ao atualizar: ' . $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'error' => 'false',
            'message' => 'Funcionario alterado com sucesso',
            'data' => $employee->toCedenteModuleArray(),
        ]);
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
