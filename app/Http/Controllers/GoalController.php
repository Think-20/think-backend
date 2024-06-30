<?php

namespace App\Http\Controllers;

use App\Goal;
use App\Http\Services\ReportsService;
use ArrayObject;
use Aws\S3\S3Client;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

use AwsS3S3Client;
use PhpParser\Node\Expr;

class GoalController extends Controller
{
    private $reportsService;
    private $reportsController;

    public function __construct(ReportsService $reportsService, ReportsController $reportsController)
    {
        $this->reportsService = $reportsService;
        $this->reportsController = $reportsController;
    }

    public function createGoal(Request $request)
    {
        if ($request->month <=  0  || $request->month >= 13) {
            return response()->json(['error' => 'true', 'message' => 'Mes invalido'], 400);
        }

        if (strlen($request->year) !== 4) {
            return response()->json(['error' => 'true', 'message' => 'Ano Invalido'], 400);
        }

        if ($request->value <=  0) {
            return response()->json(['error' => 'true', 'message' => 'Valor invalido'], 400);
        }

        if ($request->expected_value <=  0) {
            return response()->json(['error' => 'true', 'message' => 'Valor Geral invalido'], 400);
        }

        $goal = Goal::where('month', $request->month)->where('year', $request->year)->first();
        if ($goal) {
            return response()->json(['error' => 'true', 'message' => 'Meta ja cadastrada para este periodo'], 400);
        }

        $newGoal = new Goal();
        $newGoal->month = $request->month;
        $newGoal->year = $request->year;
        $newGoal->value = $request->value;
        $newGoal->expected_value = $request->expected_value;
        $newGoal->save();

        return response()->json(['error' => 'false', 'message' => 'Meta cadastrada com sucesso']);
    }

    public function testeGetS3(Request $request)
    {
        // Instantiate an Amazon S3 client.
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-2',
            'credentials' => [
                'key'    => 'AKIAU6GDZZYKK5IS5ZLO',
                'secret' => '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'
            ]
        ]);

        $bucketName = 'testedouglasprendendo';

        //Recebe o codigo da foto na request
        $key = $request->foto;
        try {
            $file = $client->getObject([
                'Bucket' => $bucketName,
                'Key' => $key,
            ]);
            $body = $file->get('Body');

            return base64_encode($body);
            //return $body;
        } catch (Exception $exception) {
            return "Failed to download $key from $bucketName with error: " . $exception->getMessage();
        }
    }

    public function testePutS3(Request $request)
    {
        // Instantiate an Amazon S3 client.
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-2',
            'credentials' => [
                'key'    => env('S3_KEY', null),
                'secret' => env('S3_SECRET', null)
            ]
        ]);

        $bucketName = env('S3_BUCKET_NAME', null);

        //Recebe a foto enviada no body
        $foto = $request->file('foto');

        $key = basename($foto);

        // Upload a publicly accessible file. The file size and type are determined by the SDK.
        try {
            $result = $client->putObject([
                'Bucket' => $bucketName,
                'Key'    => $key,
                'Body'   => fopen($foto->path(), 'r'),
                'ACL'    => 'public-read',
            ]);
            return $result;
        } catch (Exception $e) {
            return ($e);

            echo "There was an error uploading the file.n";
            echo $e->getMessage();
        }

        return response()->json(['error' => 'false', 'message' => 'Meta cadastrada com sucesso']);
    }

    public function updateGoal(Request $request)
    {
        if (!isset($request->id)) {
            return response()->json(['error' => 'true', 'message' => 'Id não informado'], 400);
        }

        if (!isset($request->value) && !isset($request->expected_value)) {
            return response()->json(['error' => 'true', 'message' => 'Valor não informado'], 400);
        }

        if (isset($request->value) && $request->value <=  0) {
            return response()->json(['error' => 'true', 'message' => 'Valor invalido'], 400);
        }

        if (isset($request->expected_value) && $request->expected_value <=  0) {
            return response()->json(['error' => 'true', 'message' => 'Valor invalido'], 400);
        }


        $goal = Goal::where('id', $request->id)->first();

        if (!$goal) {
            return response()->json(['error' => 'true', 'message' => 'Meta ' . $request->id . ' não encontrada'], 400);
        }

        if (isset($request->value)) {

            if ($request->value) {
                $goal->value = $request->value;
            }
        }

        if (isset($request->expected_value)) {
            if ($request->expected_value) {
                $goal->expected_value = $request->expected_value;
            }
        }


        $goal->save();

        return response()->json(['error' => 'false', 'message' => 'Meta atualizada com sucesso']);
    }

    public function selectGoal(Request $request, int $id = null)
    {
        if (!isset($id)) {
            $goal = Goal::get();
            if (!$goal) {
                return response()->json(['error' => 'true', 'message' => 'Meta ' . $id . ' nao encontrada'], 400);
            }

            return $goal;
        } else {
            $goal = Goal::where('id', $id)->first();

            if (!$goal) {
                return response()->json(['error' => 'true', 'message' => 'Meta ' . $id . ' nao encontrada'], 400);
            }
            return $goal;
        }
    }

    public function calendarGoals(Request $request,  $date_init,  $date_end)
    {
        $response = [];

        for ($i = 0; $i < Carbon::parse($date_end)->diffInDays(Carbon::parse($date_init)) + 1; $i++) {

            $dtFim = Carbon::parse($date_init)->addDay($i);

            //Alteração para receber dados do relatorio para q seja possivel diferenciar entre valores de internos e de externos
            $dataExtMes = [
                "date_init" => Carbon::parse($dtFim)->startOfMonth(),
                "date_end" => Carbon::parse($dtFim),
                "name" => null,
                "status" => [],
                "creation" =>  [
                    0 => "external"
                ],
                "attendance" => [],
                "job_type" => [],
                "job_activity" => [],
                "jobs_amount" => 30,
                "event" => null
            ];

            $dataExtAno = [
                "date_init" => Carbon::parse($dtFim)->startOfYear(),
                "date_end" => Carbon::parse($dtFim),
                "name" => null,
                "status" => [],
                "creation" =>  [
                    0 => "external"
                ],
                "attendance" => [],
                "job_type" => [],
                "job_activity" => [],
                "jobs_amount" => 30,
                "event" => null
            ];

            $dataIntMes = [
                "date_init" => Carbon::parse($dtFim)->startOfMonth(),
                "date_end" => Carbon::parse($dtFim),
                "name" => null,
                "status" => [],
                "creation" =>  [],
                "attendance" => [],
                "job_type" => [],
                "job_activity" => [],
                "jobs_amount" => 30,
                "event" => null
            ];

            $dataIntAno = [
                "date_init" => Carbon::parse($dtFim)->startOfYear(),
                "date_end" => Carbon::parse($dtFim),
                "name" => null,
                "status" => [],
                "creation" =>  [],
                "attendance" => [],
                "job_type" => [],
                "job_activity" => [],
                "jobs_amount" => 30,
                "event" => null
            ];

            //Dados recebidos pela função nova para criar relatorio expecificamente para o calendario
            $CurrentMonthValueExt = json_decode($this->reportsController->readCallendar($dataExtMes)->getContent(), true);
            $CurrentMonthValueInt = json_decode($this->reportsController->readCallendar($dataIntMes)->getContent(), true);

            $CurrentYearValueExt = json_decode($this->reportsController->readCallendar($dataExtAno)->getContent(), true);
            $CurrentYearValueInt = json_decode($this->reportsController->readCallendar($dataIntAno)->getContent(), true);

            //Verifica se os campos estão null e seta 0 para que n tenha problemas
            if (!isset($CurrentMonthValueExt['total_value'])) {
                $CurrentMonthValueExt['total_value'] = 0;
            }

            if (!isset($CurrentYearValueExt['total_value'])) {
                $CurrentYearValueExt['total_value'] = 0;
            }

            if (!isset($CurrentMonthValueInt['total_value'])) {
                $CurrentMonthValueInt['total_value'] = 0;
            }

            if (!isset($CurrentYearValueInt['total_value'])) {
                $CurrentYearValueInt['total_value'] = 0;
            }

            //Metas mensais e anuais
            $monthGoal =  $this->reportsService->GetGoalByMountAndYear(intval(Carbon::parse($dtFim)->subDay(1)->format('m')), intval(Carbon::parse($dtFim)->subDay(1)->format('Y')));
            $yearGoals =  $this->reportsService->GetGoalYear(intval(Carbon::parse($dtFim)->subDay(1)->format('Y')));

            /*$allMes = $this->reportsService->GetAllBudgets(["date_init" => Carbon::parse($dtFim)->startOfMonth(), "date_end" => Carbon::parse($dtFim)]);
            $allAno = $this->reportsService->GetAllBudgets(["date_init" => Carbon::parse($dtFim)->startOfYear(),  "date_end" => Carbon::parse($dtFim)]);

            

            $CurrentMonthValue = $this->reportsService->GetAllBudgets(["date_init" => Carbon::parse($dtFim)->startOfMonth(), "date_end" => Carbon::parse($dtFim)]);
            $CurrentYearValue = $this->reportsService->GetAllBudgets(["date_init" => Carbon::parse($dtFim)->startOfYear(), "date_end" => Carbon::parse($dtFim)]);

            //Caso CurrentMonthValue tenha valor nenhum, mostra 0
            if ($CurrentMonthValue->sum == null) {
                $CurrentMonthValue->sum = 0;
            }

            //Caso CurrentYearValue tenha valor nenhum, mostra 0
            if ($CurrentYearValue->sum == null) {
                $CurrentYearValue->sum = 0;
            }

            $CurrentMonthValueStand = $this->reportsService->GetStandbys(["date_init" => Carbon::parse($dtFim)->startOfMonth(), "date_end" => Carbon::parse($dtFim)]);
            $CurrentYearStand = $this->reportsService->GetStandbys(['date_init' => Carbon::parse($dtFim)->startOfYear(), 'date_end' => Carbon::parse($dtFim)->format('Y-m-d')]);

            //Caso CurrentMonthValueStand tenha valor nenhum, mostra 0
            if ($CurrentMonthValueStand->sum == null) {
                $CurrentMonthValueStand->sum = 0;
            }

            //Caso CurrentYearStand tenha valor nenhum, mostra 0
            if ($CurrentYearStand->sum == null) {
                $CurrentYearStand->sum = 0;
            }*/

            try {
                $goals = [
                    "date" => Carbon::parse($date_init)->addDay($i)->format('Y-m-d'),
                    "mes" => [
                        "atualInternoReais" => ($CurrentMonthValueInt['total_value'] - $CurrentMonthValueExt['total_value']),
                        "porcentagemInternoReais" => ((($CurrentMonthValueInt['total_value'] - $CurrentMonthValueExt['total_value']) * 100) / $monthGoal->value),
                        
                        "atualExternoReais" =>  $CurrentMonthValueExt['total_value'],
                        "porcentagemExternoReais" => ((($CurrentMonthValueExt['total_value']) * 100) / $monthGoal->value),
                        
                        "metaReais" =>  $monthGoal->value,

                        /*"porcentagemReais" => (($CurrentMonthValue->sum * 100) / $monthGoal->value),
                        //"porcentagemReais" => (($CurrentMonthValue->sum * 100) / $monthGoal->value) > 100 ? 100 : (($CurrentMonthValue->sum * 100) / $monthGoal->value),

                        //"atualReais" => $CurrentMonthValue->sum + $CurrentMonthValueStand->sum,
                        "atualReais" => $CurrentMonthValue,
                        "metaReais" =>  $monthGoal->value,*/
                        //"porcentagemJobs" => (($allMes->count * 100) / $monthGoal->expected_value),
                        //"porcentagemJobs" => (($allMes->count * 100) / $monthGoal->expected_value) > 100 ? 100 : (($allMes->count * 100) / $monthGoal->expected_value),

                        //"atualJobs" => $allMes->count,
                        //"metaJobs" => $monthGoal->expected_value,

                        //"semStand" => $CurrentMonthValue->sum,
                        //"standValor" => $CurrentMonthValueStand->sum
                    ],
                    "anual" => [

                        "atualInternoReais" => ($CurrentYearValueInt['total_value'] - $CurrentMonthValueExt['total_value']),
                        "porcentagemInternoReais" => ((($CurrentYearValueInt['total_value'] - $CurrentMonthValueExt['total_value']) * 100) / $yearGoals->value),
                        
                        "atualExternoReais" =>  $CurrentYearValueExt['total_value'],
                        "porcentagemExternoReais" => ((($CurrentYearValueExt['total_value']) * 100) / $yearGoals->value),
                        
                        "metaReais" =>  $yearGoals->value,

                        /*"porcentagemReais" => (($CurrentYearValue->sum * 100) / $yearGoals->value),
                        "atualReais" =>  $CurrentYearValue->sum + $CurrentYearStand->sum,
                        "metaReais" =>  $yearGoals->value,*/

                        //"porcentagemReais" => (($CurrentYearValue->sum * 100) / $yearGoals->value) > 100 ? 100 : (($CurrentYearValue->sum * 100) / $yearGoals->value),

                        //"porcentagemJobs" => (($allAno->sum * 100) / $yearGoals->expected_value),
                        //"porcentagemJobs" => (($allAno->sum * 100) / $yearGoals->expected_value) > 100 ? 100 : (($allAno->sum * 100) / $yearGoals->expected_value),

                        //"atualJobs" => $allAno->count,
                        //"metaJobs" => $yearGoals->expected_value,

                        //"semStand" => $CurrentYearValue->sum,
                        //"standValor" => $CurrentYearStand->sum

                    ]
                ];
            } catch (Exception $e) {
                return ($e);
            }

            array_push($response, $goals);
        }

        return $response;
    }
}
