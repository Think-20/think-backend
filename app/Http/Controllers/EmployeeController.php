<?php

namespace App\Http\Controllers;

use App\Employee;
use App\EmployeeOfficeHours;
use Illuminate\Http\Request;
use Response;

class EmployeeController extends Controller
{
    public static function all() {
        return Employee::list();
    }

    public static function filter($query) {
        return Employee::filter($query);
    }

    public static function canInsertClients() {
        return Employee::canInsertClients();
    }

    public static function registerYourself() {
        $status = false;

        try {
            $officeHours = EmployeeOfficeHours::registerYourself();
            $message = 'Horário registrado com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao cadastrar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function registerAnother(Request $request) {
        $status = false;

        try {
            $officeHours = EmployeeOfficeHours::registerAnother($request->all());
            $message = 'Horário registrado com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao cadastrar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function showAnother($employeeId) {
        return EmployeeOfficeHours::showAnother($employeeId);
    }

    public static function showYourself() {
        return EmployeeOfficeHours::showYourself();
    }

    public static function getOfficeHour($id) {
        return EmployeeOfficeHours::get($id);
    }


    public static function editOfficeHour(Request $request) {
        $status = false;

        try {
            EmployeeOfficeHours::edit($request->all());
            $message = 'Horário alterado com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function removeOfficeHour(int $id) {
        $status = false;

        try {
            EmployeeOfficeHours::remove($id);
            $message = 'Horário deletado com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao deletar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }
}
