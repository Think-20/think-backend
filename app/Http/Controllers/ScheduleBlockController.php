<?php

namespace App\Http\Controllers;

use App\ScheduleBlock;
use Illuminate\Http\Request;
use Response;
use Exception;
use DateTime;
use App\DateHelper;

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
        $date = new DateTime();
        $date1 = DateHelper::sub(new DateTime(), 31);
        $date2 = DateHelper::sum(new DateTime(), 31);

        return ScheduleBlock::where('date', '>=', $date1->format('Y-m') . '-01')
        ->where('date', '<=', $date2->format('Y-m') . '-31')
        ->get();
    }

    public static function all() {
        return ScheduleBlock::all();
    }
}
