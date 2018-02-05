<?php

namespace App\Http\Controllers;

use App\Briefing;
use Exception;
use Response;

use DB;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class BriefingController extends Controller
{
    public static function save(Request $request) {
        $status = false;
        $briefing = null;

        DB::beginTransaction();

        try {
            $briefing = Briefing::insert($request->all());
            $message = 'Briefing cadastrado com sucesso!';
            $status = true;
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            if($queryException->getCode() == 23000) {
                $message = 'Já existe um briefing idêntico cadastrado.';  
                //. $queryException->getMessage() . $queryException->getFile() . $queryException->getLine();
            } else {
                $message = 'Um erro ocorreu ao cadastrar no banco de dados.';
                //. $queryException->getMessage() . $queryException->getFile() . $queryException->getLine();
            }
        } 
        /* Catch com FileException tamanho máximo */
        catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'briefing' => $briefing
         ]), 200);
    }

    public static function edit(Request $request) {
        DB::beginTransaction();
        $status = false;

        try {
            $briefing = Briefing::edit($request->all());
            $message = 'Briefing alterado com sucesso!';
            $status = true;
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            if($queryException->getCode() == 23000) {
                $message = 'Já existe um briefing como esse cadastrado.';
            } else {
                $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
            }
        } catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function downloadFile($id, $type, $file) {
        $status = false;
        $statusNumber = 200;

        try {
            $file = Briefing::downloadFile($id, $type, $file);
            $message = 'Download de arquivo com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao fazer download: ' . $e->getMessage();
            $statusNumber = 404;
            return Response::make(json_encode([
                'message' => $message,
                'status' => $status,
                'file' => $file
             ]), $statusNumber);
        }

        return Response::make(file_get_contents($file), 200, ['Content-Type' => mime_content_type($file)]);
    }

    public static function remove(int $id) {
        $status = false;

        try {
            $briefing = Briefing::remove($id);
            $message = 'Briefing deletado com sucesso!';
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
        return Briefing::get($id);
    }

    public static function all() {
        $briefings = Briefing::list();

        return $briefings;
    }

    public static function filter($query) {
        return Briefing::filter($query);
    }
}
