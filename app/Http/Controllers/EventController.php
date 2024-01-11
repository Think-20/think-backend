<?php

namespace App\Http\Controllers;

use App\Event;
use Illuminate\Http\Request;
use Response;
use Exception;
use DB;

class EventController extends Controller
{
    public static function all(Request $request)
    {
        return Event::list($request->all());
    }

    public static function filter(Request $request)
    {
        return Event::filter($request->all());
    }

    public static function jobevents($event = null)
    {
        return Event::jobevents($event);
    }

    public static function get(int $id)
    {
        return Event::get($id);
    }

    public static function downloadFile($id, $type, $file)
    {
        try {
            $fileFound = Event::downloadFile($id, $type, $file);
            $status = true;
            return Response::make(file_get_contents($fileFound), 200, ['Content-Type' => mime_content_type($fileFound)]);
        } catch (Exception $e) {
            $message = 'Um erro ocorreu ao abrir o arquivo: ' . $e->getMessage();
            return Response::make($message, 404);
        }
    }

    public static function save(Request $request)
    {
        $status = false;

        try {
            $place = Event::insert($request->all());
            $message = 'Evento cadastrado com sucesso!';
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
            Event::edit($request->all());
            $message = 'Evento alterado com sucesso!';
            $status = true;
        } catch (Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao atualizar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }
    public static function remove(int $id)
    {
        $status = false;

        try {
            $place = Event::remove($id);
            $message = 'Evento deletado com sucesso!';
            $status = true;
        } catch (QueryException $queryException) {
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao deletar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }
}
