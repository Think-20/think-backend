<?php

namespace App\Http\Controllers;

use App\Job;
use Exception;
use Response;

use DB;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\FileHelper;
use App\JobActivity;
use Aws\S3\S3Client;
use AwsS3S3Client;
use GuzzleHttp\Client;




require __DIR__ . '/../../../vendor/autoload.php';

class JobController extends Controller
{
    public static function loadForm()
    {
        return Response::make(json_encode([
            'data' => Job::loadForm()
        ]), 200);
    }

    public static function save(Request $request)
    {

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
        /* Catch com FileException tamanho máximo */ catch (Exception $e) {
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

    public static function calculate()
    {
        $job = Job::calculate();
        return $job;
    }

    public static function edit(Request $request)
    {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $job = Job::edit($data);
            $message = 'Job alterado com sucesso!';
            $status = true;
            DB::commit();
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }

    public function consultarCnpj(Request $request, $cnpj)
    {
        $client = new Client();
        $url = "https://www.receitaws.com.br/v1/cnpj/" . $cnpj;

        try {
            $response = $client->request('GET', $url);
            $dados = json_decode($response->getBody(), true);
            return response()->json($dados);
        } catch (\Exception $e) {
            return response()->json(['error' => 'true', 'message' => 'Não foi possivel receber os dados desse CNPJ.'], 200);
        }
    }

    public static function editAvailableDate(Request $request)
    {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $job = Job::editAvailableDate($data);
            $message = 'Job alterado com sucesso!';
            $status = true;
            DB::commit();
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }

    public static function downloadFile($id, $type, $file)
    {
        try {
            $fileFound = Job::downloadFile($id, $type, $file);
            $status = true;
            return Response::make(file_get_contents($fileFound), 200, ['Content-Type' => mime_content_type($fileFound)]);
        } catch (Exception $e) {
            $message = 'Um erro ocorreu ao abrir o arquivo: ' . $e->getMessage();
            return Response::make($message, 404);
        }
    }

    public static function remove(int $id)
    {
        DB::beginTransaction();
        $status = false;

        try {
            $job = Job::remove($id);
            $message = 'Job deletado com sucesso!';
            $status = true;
            DB::commit();
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro desconhecido ocorreu ao deletar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }

    public static function get(int $id)
    {
        return Job::get($id);
    }

    public static function all()
    {
        $jobs = Job::list();

        return $jobs;
    }

    public static function filter(Request $request)
    {
        return Job::filter($request->all());
    }

    public static function feedbackUpdate(Request $request)
    {
        return Job::feedbackUpdate($request->all());
    }

    public static function feedbackEmail(Request $request)
    {
        return Job::sendFeedbackEmail($request->all());
    }

    public static function performanceLite(Request $request)
    {
        return Job::performanceLite($request->all());
    }

    public static function myEditAvailableDate(Request $request)
    {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $job = Job::myEditAvailableDate($data);
            $message = 'Job alterado com sucesso!';
            $status = true;
            DB::commit();
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }

    public static function saveMyJob(Request $request)
    {
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
        /* Catch com FileException tamanho máximo */ catch (Exception $e) {
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

    public static function editMyJob(Request $request)
    {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();

        try {
            $job = Job::editMyJob($data);
            $message = 'Job alterado com sucesso!';
            $status = true;
            DB::commit();
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
            // . $e->getFile() . $e->getLine();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }

    public static function downloadFileMyJob($id, $type, $filename)
    {
        try {
            $file = Job::downloadFileMyJob($id, $type, $filename);
            $status = true;
            return Response::make(file_get_contents($file), 200, [
                'Content-Type' => mime_content_type($file),
                'Content-Disposition: inline; filename="' . $filename . '"'
            ]);
        } catch (Exception $e) {
            $message = 'Um erro ocorreu ao abrir o arquivo: ' . $e->getMessage();
            return Response::make($message, 404);
        }
    }

    public static function removeMyJob(int $id)
    {
        DB::beginTransaction();
        $status = false;

        try {
            $job = Job::removeMyJob($id);
            $message = 'Job deletado com sucesso!';
            $status = true;
            DB::commit();
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao deletar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro desconhecido ocorreu ao deletar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }

    public static function getMyJob(int $id)
    {
        return Job::getMyJob($id);
    }

    public static function allMyJob()
    {
        $jobs = Job::listMyJob();
        return $jobs;
    }

    public static function filterMyJob(Request $request)
    {
        return Job::filterMyJob($request->all());
    }
     
    public static function workflowAtendimento(Request $request)
    {
        $params = $request->all();
        $page = $request->query('page', 1);
        
        // Extrair filtros
        $initialDate = isset($params['initial_date']) ? substr($params['initial_date'], 0, 10) : null;
        $finalDate = isset($params['final_date']) ? substr($params['final_date'], 0, 10) : null;
        $status = isset($params['status']) ? $params['status'] : null;
        $clientName = isset($params['clientName']) ? $params['clientName'] : null;
        $attendanceId = isset($params['attendance']['id']) ? $params['attendance']['id'] : null;
        $creationId = isset($params['creation']['id']) ? $params['creation']['id'] : null;
        $jobTypeId = isset($params['job_type']['id']) ? $params['job_type']['id'] : null;
        
        $jobs = Job::selectRaw('job.*')
            ->with(
                'job_activity',
                'job_type',
                'client',
                'main_expectation',
                'levels',
                'how_come',
                'agency',
                'attendance',
                'competition',
                'files',
                'status',
                'creation'
            )
            ->with(['creation.items' => function ($query) {
                $query->limit(1);
            }]);
            
        
        // Filtro por status do workflow
        if ($status == 'AGUARDANDO_CRIATIVO') {
            $jobs->whereIn('creation_status', [2, 3, 4]);
        } else {
            $jobs->where('status_id', '=', 1)
                 ->where(function($query) {
                     $query->whereNull('creation_status')
                           ->orWhere('creation_status', '=', 1);
                 });
        }
        
        // Filtros adicionais
        if (!is_null($clientName)) {
            $jobs->whereHas('client', function ($query) use ($clientName) {
                $query->where('fantasy_name', 'LIKE', '%' . $clientName . '%');
                $query->orWhere('name', 'LIKE', '%' . $clientName . '%');
            });
            $jobs->orWhere('not_client', 'LIKE', '%' . $clientName . '%');
        }
        
        if (!is_null($attendanceId)) {
            $jobs->whereHas('attendance', function ($query) use ($attendanceId) {
                $query->where('id', '=', $attendanceId);
            });
        }

        if (!is_null($creationId)) {
            $jobs->whereHas('creation', function ($query) use ($creationId) {
                $query->where('responsible_id', '=', $creationId);
            });
        }
        
        if (!is_null($jobTypeId)) {
            $jobs->where('job_type_id', '=', $jobTypeId);
        }

        if (!is_null($initialDate)) {
            
            $jobs->whereDate('job.created_at', '>=', $initialDate);
        }

        if (!is_null($finalDate)) {
            $jobs->whereDate('job.created_at', '<=', $finalDate);
        }

        $jobs->orderBy('job.created_at', 'DESC');
        
        $paginate = $jobs->paginate(50, ['*'], 'page', $page);
        
        foreach ($paginate as $job) {
            $job->responsibles();
        }
        
        return Response::make(json_encode([
            'pagination' => [
                'data' => $paginate->items(),
                'total' => $paginate->total(),
                'last_page' => $paginate->lastPage()
            ]
        ]), 200);
    }

    public static function workflowCriativo(Request $request)
    {
        $params = $request->all();
        $page = $request->query('page', 1);
        
        // Extrair filtros
        $initialDate = isset($params['initial_date']) ? substr($params['initial_date'], 0, 10) : null;
        $finalDate = isset($params['final_date']) ? substr($params['final_date'], 0, 10) : null;
        $creationStatus = isset($params['creation_status']) ? $params['creation_status'] : null;
        $clientName = isset($params['clientName']) ? $params['clientName'] : null;
        $attendanceId = isset($params['attendance']['id']) ? $params['attendance']['id'] : null;
        $creationId = isset($params['creation']['id']) ? $params['creation']['id'] : null;
        $jobTypeId = isset($params['job_type']['id']) ? $params['job_type']['id'] : null;
        
        $jobs = Job::selectRaw('job.*')
            ->with(
                'job_activity',
                'job_type',
                'client',
                'main_expectation',
                'levels',
                'how_come',
                'agency',
                'attendance',
                'competition',
                'files',
                'status',
                'creation'
            )
            ->with(['creation.items' => function ($query) {
                $query->limit(1);
            }]);
        
        // Job deve estar com status "stand-by/criativo" (status_id = 1)
        $jobs->where('status_id', '=', 1);
        
        // Filtro por creation_status se fornecido
        if (!is_null($creationStatus)) {
            $jobs->where('creation_status', '=', $creationStatus);
        }
        
        // Filtros adicionais
        if (!is_null($clientName)) {
            $jobs->whereHas('client', function ($query) use ($clientName) {
                $query->where('fantasy_name', 'LIKE', '%' . $clientName . '%');
                $query->orWhere('name', 'LIKE', '%' . $clientName . '%');
            });
            $jobs->orWhere('not_client', 'LIKE', '%' . $clientName . '%');
        }
        
        if (!is_null($attendanceId)) {
            $jobs->whereHas('attendance', function ($query) use ($attendanceId) {
                $query->where('id', '=', $attendanceId);
            });
        }
        
        if (!is_null($creationId)) {
            $jobs->whereHas('creation', function ($query) use ($creationId) {
                $query->where('responsible_id', '=', $creationId);
            });
        }
        
        if (!is_null($jobTypeId)) {
            $jobs->where('job_type_id', '=', $jobTypeId);
        }
        
        if (!is_null($initialDate)) {
            $jobs->whereHas('creation.items', function ($query) use ($initialDate) {
                $query->where('date', '>=', $initialDate);
            });
        }
        
        if (!is_null($finalDate)) {
            $jobs->whereHas('creation.items', function ($query) use ($finalDate) {
                $query->where('date', '<=', $finalDate);
            });
        }
        
        $jobs->orderBy('job.created_at', 'DESC');
        
        $paginate = $jobs->paginate(50, ['*'], 'page', $page);
        
        foreach ($paginate as $job) {
            $job->responsibles();
        }
        
        return Response::make(json_encode([
            'pagination' => [
                'data' => $paginate->items(),
                'total' => $paginate->total(),
                'last_page' => $paginate->lastPage()
            ]
        ]), 200);
    }

    public static function workflowProducao(Request $request)
    {
        $params = $request->all();
        $page = $request->query('page', 1);
        
        // Extrair filtros
        $initialDate = isset($params['initial_date']) ? substr($params['initial_date'], 0, 10) : null;
        $finalDate = isset($params['final_date']) ? substr($params['final_date'], 0, 10) : null;
        $productionStatus = isset($params['production_status']) ? $params['production_status'] : null;
        $clientName = isset($params['clientName']) ? $params['clientName'] : null;
        $attendanceId = isset($params['attendance']['id']) ? $params['attendance']['id'] : null;
        $creationId = isset($params['creation']['id']) ? $params['creation']['id'] : null;
        $jobTypeId = isset($params['job_type']['id']) ? $params['job_type']['id'] : null;
        
        // Verificar se o aceite financeiro do checkin foi realizado usando join
        $jobs = Job::selectRaw('job.*')
            ->join('checkin', 'checkin.job_id', '=', 'job.id')
            ->where('checkin.financial_acceptance', '=', true)
            ->distinct()
            ->with(
                'job_activity',
                'job_type',
                'client',
                'main_expectation',
                'levels',
                'how_come',
                'agency',
                'attendance',
                'competition',
                'files',
                'status',
                'creation'
            )
            ->with(['creation.items' => function ($query) {
                $query->limit(1);
            }]);
        
        // Job deve estar com status "Aprovado" (status_id = 3)
        $jobs->where('job.status_id', '=', 3);
        
        // Filtros adicionais
        if (!is_null($clientName)) {
            $jobs->whereHas('client', function ($query) use ($clientName) {
                $query->where('fantasy_name', 'LIKE', '%' . $clientName . '%');
                $query->orWhere('name', 'LIKE', '%' . $clientName . '%');
            });
            $jobs->orWhere('job.not_client', 'LIKE', '%' . $clientName . '%');
        }
        
        if (!is_null($attendanceId)) {
            $jobs->whereHas('attendance', function ($query) use ($attendanceId) {
                $query->where('id', '=', $attendanceId);
            });
        }
        
        if (!is_null($creationId)) {
            $jobs->whereHas('creation', function ($query) use ($creationId) {
                $query->where('responsible_id', '=', $creationId);
            });
        }
        
        if (!is_null($jobTypeId)) {
            $jobs->where('job.job_type_id', '=', $jobTypeId);
        }
        
        if (!is_null($initialDate)) {
            $jobs->whereHas('creation.items', function ($query) use ($initialDate) {
                $query->where('date', '>=', $initialDate);
            });
        }
        
        if (!is_null($finalDate)) {
            $jobs->whereHas('creation.items', function ($query) use ($finalDate) {
                $query->where('date', '<=', $finalDate);
            });
        }
        
        // Filtro por production_status se fornecido
        if (!is_null($productionStatus)) {
            $jobs->where('job.production_status', '=', $productionStatus);
        }
        
        $jobs->orderBy('job.created_at', 'DESC');
        
        $paginate = $jobs->paginate(50, ['*'], 'page', $page);
        
        foreach ($paginate as $job) {
            $job->responsibles();
        }
        
        return Response::make(json_encode([
            'pagination' => [
                'data' => $paginate->items(),
                'total' => $paginate->total(),
                'last_page' => $paginate->lastPage()
            ]
        ]), 200);
    }

    public static function workflowCriativoUpdate(Request $request)
    {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();
        
        try {
            $jobId = isset($data['job_id']) ? $data['job_id'] : null;
            $creationStatus = isset($data['creation_status']) ? $data['creation_status'] : null;
            
            if (is_null($jobId) || is_null($creationStatus)) {
                throw new Exception('job_id e creation_status são obrigatórios');
            }
            
            $job = Job::find($jobId);
            
            if (!$job) {
                throw new Exception('Job não encontrado');
            }
            
            $job->creation_status = $creationStatus;
            
            // Quando o job for movido para "Finalizado" no workflow criativo,
            // o status do job deve passar para "Negociação avançada" (status_id = 5)
            // Assumindo que creation_status = 5 representa "Finalizado"
            // Ajuste este valor conforme a regra de negócio específica
            if ($creationStatus == 5) {
                $job->status_id = 5; // Negociação avançada
            }
            
            $job->save();
            
            $message = 'Job atualizado com sucesso!';
            $status = true;
            DB::commit();
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
        }
        
        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }

    public static function workflowProducaoUpdate(Request $request)
    {
        DB::beginTransaction();
        $status = false;
        $data = $request->all();
        
        try {
            $jobId = isset($data['job_id']) ? $data['job_id'] : null;
            $productionStatus = isset($data['production_status']) ? $data['production_status'] : null;
            
            if (is_null($jobId) || is_null($productionStatus)) {
                throw new Exception('job_id e production_status são obrigatórios');
            }
            
            $job = Job::find($jobId);
            
            if (!$job) {
                throw new Exception('Job não encontrado');
            }
            
            $job->production_status = $productionStatus;
            $job->save();
            
            $message = 'Job atualizado com sucesso!';
            $status = true;
            DB::commit();
        } catch (QueryException $queryException) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar no banco de dados. ' . $queryException->getMessage();
        } catch (Exception $e) {
            DB::rollBack();
            $message = 'Um erro ocorreu ao atualizar: ' . $e->getMessage();
        }
        
        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
        ]), 200);
    }

        
}
