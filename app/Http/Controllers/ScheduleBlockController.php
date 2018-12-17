<?php

namespace App\Http\Controllers;

use App\ScheduleBlock;
use Illuminate\Http\Request;
use Response;
use Exception;

class ScheduleBlockController extends Controller
{
    public static function save(Request $request) {
        $status = false;
        $scheduleBlock = null;

        try {
            $scheduleBlock = ScheduleBlock::insert($request->all());
            $message = 'Data registrada com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'scheduleBlock' => $scheduleBlock
         ]), 200);
    }

    public static function remove(int $id) {
        $status = false;

        try {
            ScheduleBlock::remove($id);
            $message = 'Data removida com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao remover: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status
         ]), 200);
    }

    public static function valid() {
        return ScheduleBlock::valid();
    }

    public static function all() {
        return ScheduleBlock::all();
    }
}
