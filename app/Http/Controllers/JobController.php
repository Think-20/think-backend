<?php

namespace App\Http\Controllers;

use App\Job;
use Exception;
use Response;

use DB;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\FileHelper;

class JobController extends Controller
{
    public static function loadForm() {        
        return Response::make(json_encode([
            'data' => Job::loadForm()
         ]), 200); 
    }
    
    public static function save(Request $request) {
        $data = $request->all();
        $status = false;
        $job = null;

        DB::beginTransaction();

        try {
            $job = Job::insert($data);
            $code = str_pad($job->code, 4, '0', STR_PAD_LEFT) . '/' . $job->created_at->format('Y');
            $message = 'Job ' . $code . ' cadastrado com sucesso!';
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
            'job' => $job
         ]), 200);
    }

    public static function edit(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();
        //$oldJob = Job::find($request->id);
        //$oldChild = Job::getJobChild($oldJob);

        try {
            $job = Job::edit($data);
            $message = 'Job alterado com sucesso!';
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

    public static function editAvailableDate(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $job = Job::editAvailableDate($data);
            $message = 'Job alterado com sucesso!';
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

    public static function downloadFile($id, $type, $file) {
        try {
            $fileFound = Job::downloadFile($id, $type, $file);
            $status = true;
            return Response::make(file_get_contents($fileFound), 200, ['Content-Type' => mime_content_type($fileFound)]);
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao abrir o arquivo: ' . $e->getMessage();
            return Response::make($message, 404);
        }
    }

    public static function remove(int $id) {
        DB::beginTransaction();
        $status = false;

        try {
            $job = Job::remove($id);
            $message = 'Job deletado com sucesso!';
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
        return Job::get($id);
    }

    public static function all() {
        $jobs = Job::list();

        return $jobs;
    }

    public static function filter(Request $request) {
        return Job::filter($request->all());
    }

    public static function myEditAvailableDate(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $job = Job::myEditAvailableDate($data);
            $message = 'Job alterado com sucesso!';
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

    public static function saveMyJob(Request $request) {
        $data = $request->all();
        $status = false;
        $job = null;

        DB::beginTransaction();

        try {
            $job = Job::insert($data);
            $code = str_pad($job->code, 4, '0', STR_PAD_LEFT) . '/' . $job->created_at->format('Y');
            $message = 'Job ' . $code . ' cadastrado com sucesso!';
            $status = true;
            DB::commit();
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
            'job' => $job
         ]), 200);
    }

    public static function editMyJob(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();
        //$oldJob = Job::find($request->id);
        //$oldChild = Job::getJobChild($oldJob);

        try {
            $job = Job::editMyJob($data);
            $message = 'Job alterado com sucesso!';
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

    public static function downloadFileMyJob($id, $type, $filename) {
        try {
            $file = Job::downloadFileMyJob($id, $type, $filename);
            $status = true;
            return Response::make(file_get_contents($file), 200, [
                'Content-Type' => mime_content_type($file), 
                'Content-Disposition: inline; filename="' . $filename . '"'
            ]);
        } catch(Exception $e) {
            $message = 'Um erro ocorreu ao abrir o arquivo: ' . $e->getMessage();
            return Response::make($message, 404);
        }
    }

    public static function removeMyJob(int $id) {
        DB::beginTransaction();
        $status = false;

        try {
            $job = Job::removeMyJob($id);
            $message = 'Job deletado com sucesso!';
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

    public static function getMyJob(int $id) {
        return Job::getMyJob($id);
    }

    public static function allMyJob() {
        $jobs = Job::listMyJob();
        return $jobs;
    }

    public static function filterMyJob(Request $request) {
        return Job::filterMyJob($request->all());
    }
}
