<?php

namespace App\Http\Controllers;

use App\Employee;
use App\User;
use App\Timecard;
use Illuminate\Http\Request;
use Response;
use Exception;

class TimecardController extends Controller
{
    public static function registerYourself(Request $request) {
        $status = false;

        try {
            $officeHours = Timecard::registerYourself($request->all());
            $message = 'Horário registrado com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function statusYourself() {
        return Timecard::statusYourself();
    }

    public static function registerAnother(Request $request) {
        $status = false;

        try {
            $officeHours = Timecard::registerAnother($request->all());
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

    public static function showAnother(Request $request) {
        return [
            'timecards' => Timecard::showAnother($request->all()),
            'balance' => Timecard::balance($request->employee['id'])
        ];
    }

    public static function showYourself(Request $request) {
        return [
            'timecards' => Timecard::showYourself($request->all()),
            'balance' => Timecard::balance(User::logged()->employee->id)
        ];
    }

    public static function getOfficeHour($id) {
        return Timecard::get($id);
    }

    public static function showApprovalsPending() {
        return Timecard::showApprovalsPending();
    }

    public static function approvePending($id) {
        $status = false;

        try {
            Timecard::approvePending($id);
            $message = 'Horário aprovado com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao aprovar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }
    
    public static function editOfficeHour(Request $request) {
        $status = false;

        //try {
            Timecard::edit($request->all());
            $message = 'Horário alterado com sucesso!';
            $status = true;
        //} catch(Exception $e) {
            //$message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
        //}

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function removeOfficeHour(int $id) {
        $status = false;

        try {
            Timecard::remove($id);
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
