<?php

namespace App\Http\Controllers;

use App\Task;
use App\TaskItem;
use Exception;
use Response;

use DB;
use PDF;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class TaskController extends Controller
{    
    public static function getNextAvailableDate($availableDate, $jobActivity) {
        return Response::make(json_encode(Task::getNextAvailableDate($availableDate, $jobActivity)), 200); 
    }

    public static function getNextAvailableDates(Request $request) {
        return Response::make(json_encode(Task::getNextAvailableDates($request->all())), 200); 
    }

    public static function save(Request $request) {
        $data = $request->all();
        $status = false;
        $task = null;

        DB::beginTransaction();

        try {
            $task = Task::insert($data);
            $message = 'Cronograma cadastrado com sucesso!';
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
            'task' => $task
         ]), 200);
    }

    public static function insertDerived(Request $request) {
        $data = $request->all();
        $status = false;
        $task = null;

        DB::beginTransaction();

        try {
            $task = Task::insertDerived($data);
            $message = 'Agenda cadastrada com sucesso!';
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
            'task' => $task
         ]), 200);
    }

    public static function edit(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $task = Task::edit($data);
            $message = 'Cronograma alterado com sucesso!';
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

    public static function editValues(Request $request) {
        
        dd('teste');
        dd($request->all());
        
        DB::beginTransaction();
        $status = false;
        $data = $request->all();


        try {
            $task = Task::editValues($data);
            
            $message = 'Task alterada com sucesso!';
            $status = true;
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
        }

        return response()->json([
            'message' => $message,
            'status' => $status,
         ], 200);
    }

    public static function editAvailableDate(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $task = Task::editAvailableDate($data);
            $message = 'Agenda alterada com sucesso!';
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

    public static function memorialPdf($id) {
        $task = Task::find($id);

        setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');

        return PDF::loadView('pdf.memorial', [
            'task' => $task,
            'bg' => base64_encode(file_get_contents(public_path() . '/assets/images/timbrado.jpg'))
        ])
        ->stream($task->job->getJobName() . ' - Memorial descritivo.pdf');
    }

    public static function responsiblesByActivity($jobActivityId) {
        return Task::responsiblesByActivity($jobActivityId);
    }

    public static function remove(int $id) {
        DB::beginTransaction();
        $status = false;

        try {
            $task = Task::remove($id);
            $message = 'Tarefa no cronograma deletada com sucesso!';
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
        return Task::get($id);
    }

    public static function all() {
        $tasks = Task::list();

        return $tasks;
    }

    public static function filterItems(Request $request) {
        return TaskItem::filter($request->all());
    }

    public static function filter(Request $request) {
        return Task::filter($request->all());
    }

    public static function filterMyTask(Request $request) {
        return Task::filterMyTask($request->all());
    }

    public static function filterMyItems(Request $request) {
        return TaskItem::filterMyItems($request->all());
    }

    public static function updatedInfo() {
        return Task::updatedInfo();
    }


    public static function myEditAvailableDate(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $task = Task::myEditAvailableDate($data);
            $message = 'Cronograma alterado com sucesso!';
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

    public static function saveMyTask(Request $request) {
        $data = $request->all();
        $status = false;
        $task = null;

        DB::beginTransaction();

        try {
            $task = Task::insert($data);
            $message = 'Cronograma cadastrado com sucesso!';
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
            'task' => $task
         ]), 200);
    }

    public static function editMyTask(Request $request) {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $task = Task::editMyTask($data);
            $message = 'Cronograma alterado com sucesso!';
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

    public static function removeMyTask(int $id) {
        DB::beginTransaction();
        $status = false;

        try {
            $task = Task::removeMyTask($id);
            $message = 'Tarefa no cronograma deletada com sucesso!';
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

    public static function getMyTask(int $id) {
        return Task::getMyTask($id);
    }

    public static function allMyTask() {
        $tasks = Task::listMyTask();

        return $tasks;
    }
}
