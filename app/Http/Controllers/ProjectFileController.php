<?php

namespace App\Http\Controllers;

use App\ProjectFile;
use Exception;
use Response;

use DB;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\FileHelper;

class ProjectFileController extends Controller
{
    public static function save(Request $request) {
        $data = $request->all();
        $status = false;
        $project_file = null;

        DB::beginTransaction();

        try {
            $project_file = ProjectFile::insert($data);
            $message = 'Arquivo inserido com sucesso!';
            DB::commit();
            $status = true;
        } 
        /* Catch com FileException tamanho máximo */
        catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
             //. $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'project_file' => $project_file
         ]), 200);
    }

    public static function edit(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();
        //$oldProjectFile = ProjectFile::find($request->id);
        //$oldChild = ProjectFile::getProjectFileChild($oldProjectFile);

        try {
            $project_file = ProjectFile::edit($data);
            $message = 'Arquivo alterado com sucesso!';
            $status = true;
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
    
    
    public static function remove(int $id) {
        DB::beginTransaction();
        $status = false;

        try {
            $project_file = ProjectFile::remove($id);
            $message = 'Arquivo removido com sucesso!';
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
}
