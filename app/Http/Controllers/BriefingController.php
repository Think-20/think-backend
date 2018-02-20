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
    public static function loadForm() {
        return Response::make(json_encode([
            'data' => Briefing::loadForm()
         ]), 200); 
    }

    public static function save(Request $request) {
        $data = $request->all();
        $status = false;
        $briefing = null;

        DB::beginTransaction();

        try {
            $briefing = Briefing::insert($data);
            $code = str_pad($briefing->code, 4, '0', STR_PAD_LEFT) . '/' . $briefing->created_at->format('Y');
            $message = 'Briefing ' . $code . ' cadastrado com sucesso!';
            $status = true;
            $briefing->saveFiles($data);
            $briefing->saveFilesChild($data);
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            if($queryException->getCode() == 23000) {
                $message = 'Já existe um briefing idêntico cadastrado.';  
                //. $queryException->getMessage() . $queryException->getFile() . $queryException->getLine();
            } else {
                $message = 'Um erro ocorreu ao cadastrar no banco de dados.' . $queryException->getMessage();
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
        $data = $request->all();
        $oldBriefing = Briefing::find($request->id);
        $oldChild = Briefing::getBriefingChild($oldBriefing);

        try {
            $briefing = Briefing::edit($data);
            $message = 'Briefing alterado com sucesso!';
            $status = true;
            $briefing->editFiles($oldBriefing, $data);
            $briefing->editFilesChild($oldChild, $data);
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function downloadFile($id, $type, $file) {
        try {
            $file = Briefing::downloadFile($id, $type, $file);
            $status = true;
            return Response::make(file_get_contents($file), 200, ['Content-Type' => mime_content_type($file)]);
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao abrir o arquivo: ' . $e->getMessage();
            return Response::make($message, 404);
        }
    }

    public static function remove(int $id) {
        DB::beginTransaction();
        $status = false;

        try {
            $briefing = Briefing::remove($id);
            $message = 'Briefing deletado com sucesso!';
            $status = true;
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            DB::rollBack();
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

    public static function saveMyBriefing(Request $request) {
        $data = $request->all();
        $status = false;
        $briefing = null;

        DB::beginTransaction();

        try {
            $briefing = Briefing::insert($data);
            $message = 'Briefing ' . $briefing->id . ' cadastrado com sucesso!';
            $status = true;
            $briefing->saveFiles($data);
            $briefing->saveFilesChild($data);
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            if($queryException->getCode() == 23000) {
                $message = 'Já existe um briefing idêntico cadastrado.';  
                //. $queryException->getMessage() . $queryException->getFile() . $queryException->getLine();
            } else {
                $message = 'Um erro ocorreu ao cadastrar no banco de dados.' . $queryException->getMessage();
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

    public static function editMyBriefing(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();
        $oldBriefing = Briefing::find($request->id);
        $oldChild = Briefing::getBriefingChild($oldBriefing);

        try {
            $briefing = Briefing::editMyBriefing($data);
            $message = 'Briefing alterado com sucesso!';
            $status = true;
            $briefing->editFiles($oldBriefing, $data);
            $briefing->editFilesChild($oldChild, $data);
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function downloadFileMyBriefing($id, $type, $file) {
        try {
            $file = Briefing::downloadFileMyBriefing($id, $type, $file);
            $status = true;
            return Response::make(file_get_contents($file), 200, ['Content-Type' => mime_content_type($file)]);
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao abrir o arquivo: ' . $e->getMessage();
            return Response::make($message, 404);
        }
    }

    public static function removeMyBriefing(int $id) {
        DB::beginTransaction();
        $status = false;

        try {
            $briefing = Briefing::removeMyBriefing($id);
            $message = 'Briefing deletado com sucesso!';
            $status = true;
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro desconhecido ocorreu ao deletar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function getMyBriefing(int $id) {
        return Briefing::getMyBriefing($id);
    }

    public static function allMyBriefing() {
        $briefings = Briefing::listMyBriefing();

        return $briefings;
    }

    public static function filterMyBriefing($query) {
        return Briefing::filterMyBriefing($query);
    }
}
