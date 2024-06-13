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
<<<<<<< HEAD
{
    public static function getNextAvailableDate($availableDate, $jobActivity)
    {
        return Response::make(json_encode(Task::getNextAvailableDate($availableDate, $jobActivity)), 200);
    }

    public static function getNextAvailableDates(Request $request)
    {
        return Response::make(json_encode(Task::getNextAvailableDates($request->all())), 200);
    }

    public static function save(Request $request)
    {
=======
{    
    public static function getNextAvailableDate($availableDate, $jobActivity) {
        return Response::make(json_encode(Task::getNextAvailableDate($availableDate, $jobActivity)), 200); 
    }

    public static function getNextAvailableDates(Request $request) {
        return Response::make(json_encode(Task::getNextAvailableDates($request->all())), 200); 
    }

    public static function save(Request $request) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        $data = $request->all();
        $status = false;
        $task = null;

        DB::beginTransaction();

        try {
            $task = Task::insert($data);
            $message = 'Cronograma cadastrado com sucesso!';
            DB::commit();
            $status = true;
<<<<<<< HEAD
        }
        /* Catch com FileException tamanho máximo */ catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
            //. $e->getFile() . $e->getLine();
=======
        } 
        /* Catch com FileException tamanho máximo */
        catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
             //. $e->getFile() . $e->getLine();
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'task' => $task
<<<<<<< HEAD
        ]), 200);
    }

    public static function insertDerived(Request $request)
    {
=======
         ]), 200);
    }

    public static function insertDerived(Request $request) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        $data = $request->all();
        $status = false;
        $task = null;

        DB::beginTransaction();

        try {
            $task = Task::insertDerived($data);
            $message = 'Agenda cadastrada com sucesso!';
            DB::commit();
            $status = true;
<<<<<<< HEAD
        }
        /* Catch com FileException tamanho máximo */ catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
            //. $e->getFile() . $e->getLine();
=======
        } 
        /* Catch com FileException tamanho máximo */
        catch(Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
             //. $e->getFile() . $e->getLine();
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'task' => $task
<<<<<<< HEAD
        ]), 200);
    }

    public static function edit(Request $request)
    {
=======
         ]), 200);
    }

    public static function edit(Request $request) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $task = Task::edit($data);
            $message = 'Cronograma alterado com sucesso!';
            $status = true;
            DB::commit();
<<<<<<< HEAD
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
=======
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
<<<<<<< HEAD
        ]), 200);
    }

    public static function editValues(Request $request)
    {
=======
         ]), 200);
    }

    public static function editValues(Request $request) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
<<<<<<< HEAD
            $task = Task::editValuesBudget($data);

            $message = 'Task alterada com sucesso!';
            $status = true;
            DB::commit();
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
=======
            $task = Task::editValues($data);
            
            $message = 'Task alterada com sucesso!';
            $status = true;
            DB::commit();
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
        }

        return response()->json([
            'message' => $message,
            'status' => $status,
<<<<<<< HEAD
        ], 200);
    }

    public static function editAvailableDate(Request $request)
    {
=======
         ], 200);
    }

    public static function editAvailableDate(Request $request) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $task = Task::editAvailableDate($data);
            $message = 'Agenda alterada com sucesso!';
            $status = true;
            DB::commit();
<<<<<<< HEAD
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
=======
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
<<<<<<< HEAD
        ]), 200);
    }

    public static function memorialPdf($id)
    {
=======
         ]), 200);
    }

    public static function memorialPdf($id) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        $task = Task::find($id);

        setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');

        return PDF::loadView('pdf.memorial', [
            'task' => $task,
            'bg' => base64_encode(file_get_contents(public_path() . '/assets/images/timbrado.jpg'))
        ])
<<<<<<< HEAD
            ->stream($task->job->getJobName() . ' - Memorial descritivo.pdf');
    }

    public static function responsiblesByActivity($jobActivityId)
    {
        return Task::responsiblesByActivity($jobActivityId);
    }

    public static function remove(int $id)
    {
=======
        ->stream($task->job->getJobName() . ' - Memorial descritivo.pdf');
    }

    public static function responsiblesByActivity($jobActivityId) {
        return Task::responsiblesByActivity($jobActivityId);
    }

    public static function remove(int $id) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        DB::beginTransaction();
        $status = false;

        try {
            $task = Task::remove($id);
            $message = 'Tarefa no cronograma deletada com sucesso!';
            $status = true;
            DB::commit();
<<<<<<< HEAD
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
=======
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
            DB::rollBack();
            $message = 'Um erro desconhecido ocorreu ao deletar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
<<<<<<< HEAD
        ]), 200);
    }

    public static function get(int $id)
    {
        return Task::get($id);
    }

    public static function all()
    {
=======
         ]), 200);
    }

    public static function get(int $id) {
        return Task::get($id);
    }

    public static function all() {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        $tasks = Task::list();

        return $tasks;
    }

<<<<<<< HEAD
    public static function filterItems(Request $request)
    {
        return TaskItem::filter($request->all());
    }

    public static function filter(Request $request)
    {
        return Task::filter($request->all());
    }

    public static function filterMyTask(Request $request)
    {
        return Task::filterMyTask($request->all());
    }

    public static function filterMyItems(Request $request)
    {
        return TaskItem::filterMyItems($request->all());
    }

    public static function updatedInfo()
    {
=======
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
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        return Task::updatedInfo();
    }


<<<<<<< HEAD
    public static function myEditAvailableDate(Request $request)
    {
=======
    public static function myEditAvailableDate(Request $request) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $task = Task::myEditAvailableDate($data);
            $message = 'Cronograma alterado com sucesso!';
            $status = true;
            DB::commit();
<<<<<<< HEAD
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
=======
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
<<<<<<< HEAD
        ]), 200);
    }

    public static function saveMyTask(Request $request)
    {
=======
         ]), 200);
    }

    public static function saveMyTask(Request $request) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        $data = $request->all();
        $status = false;
        $task = null;

        DB::beginTransaction();

        try {
            $task = Task::insert($data);
            $message = 'Cronograma cadastrado com sucesso!';
            $status = true;
            DB::commit();
<<<<<<< HEAD
        }
        /* Catch com FileException tamanho máximo */ catch (Exception $e) {
=======
        } 
        /* Catch com FileException tamanho máximo */
        catch(Exception $e) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
            DB::rollBack();
            $message = 'Um erro ocorreu ao cadastrar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
            'task' => $task
<<<<<<< HEAD
        ]), 200);
    }

    public static function editMyTask(Request $request)
    {
=======
         ]), 200);
    }

    public static function editMyTask(Request $request) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $task = Task::editMyTask($data);
            $message = 'Cronograma alterado com sucesso!';
            $status = true;
            DB::commit();
<<<<<<< HEAD
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
=======
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
<<<<<<< HEAD
        ]), 200);
    }

    public static function removeMyTask(int $id)
    {
=======
         ]), 200);
    }

    public static function removeMyTask(int $id) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        DB::beginTransaction();
        $status = false;

        try {
            $task = Task::removeMyTask($id);
            $message = 'Tarefa no cronograma deletada com sucesso!';
            $status = true;
            DB::commit();
<<<<<<< HEAD
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
=======
        } catch(QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch(Exception $e) {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
            DB::rollBack();
            $message = 'Um erro desconhecido ocorreu ao deletar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
<<<<<<< HEAD
        ]), 200);
    }

    public static function getMyTask(int $id)
    {
        return Task::getMyTask($id);
    }

    public static function allMyTask()
    {
=======
         ]), 200);
    }

    public static function getMyTask(int $id) {
        return Task::getMyTask($id);
    }

    public static function allMyTask() {
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
        $tasks = Task::listMyTask();

        return $tasks;
    }
}
