<?php

namespace App\Http\Controllers;

use App\ProjectFile;
use App\User;
use Exception;
use Response;

use DB;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

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
            'files' => $project_file
         ]), 200);
    }

    public static function saveMultiple(Request $request) {
        $data = $request->all();
        $status = false;
        $projectFiles = null;
        
        if(User::logged()->employee->department->description == "Atendimento"){
            if(($request[0]['task']['job_activity']['description'] != "Projeto externo")){
                throw new Exception('Atendimento não tem permissão para fazer upload em projetos que não sejam Externos');
            }
        }

        DB::beginTransaction();

        try {
            $projectFiles = ProjectFile::insertAll($data);
            $message = 'Arquivos inseridos com sucesso!';
            DB::commit();
            $status = true;
        } 
        /* Catch com FileException tamanho máximo */
        catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ('.$e->getFile() .' '. $e->getLine().') ao cadastrar: ' . $e->getMessage();
             //. $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'files' => $projectFiles
         ]), 200);
    }

    public static function edit(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

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

    public static function downloadFile($id) {
        try {
            $fileFound = ProjectFile::downloadFile($id);
            $status = true;
            return Response::make(file_get_contents($fileFound), 200, ['Content-Type' => mime_content_type($fileFound)]);
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao abrir o arquivo: ' . $e->getMessage();
            return Response::make($message, 404);
        }
    }

    public static function downloadAll($taskId) {
        try {
            $fileFound = ProjectFile::downloadAllFiles($taskId);
            $status = true;
            return Response::make(file_get_contents($fileFound), 200, ['Content-Type' => mime_content_type($fileFound)]);
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao abrir o arquivo: ' . $e->getMessage();
            return Response::make($message, 404);
        }
    }
}
