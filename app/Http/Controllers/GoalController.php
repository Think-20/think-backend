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
    public function __construct(ReportsService $reportsService)
    {
        $this->reportsService = $reportsService;
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

            $dtInicio = Carbon::parse($date_init)/*->subDay(1)*/;
            $dtFim = Carbon::parse($date_init)->addDay($i);

            $allMes = $this->reportsService->GetAllBudgets(["date_init" => $dtInicio->startOfMonth(), "date_end" => $dtFim]);
            $allAno = $this->reportsService->GetAllBudgets(["date_init" => Carbon::parse($date_init)->startOfYear(), "date_end" => $dtFim]);

            $monthGoal =  $this->reportsService->GetGoalByMountAndYear(intval($dtFim->format('m')), intval($dtFim->format('Y')));
            $yearGoals =  $this->reportsService->GetGoalYear(intval($dtFim->format('Y')));

            $CurrentMonthValue = $this->reportsService->GetAllBudgets(['date_init' => Carbon::parse($dtFim)->startOfMonth()->format('Y-m-d'), 'date_end' => $dtFim->format('Y-m-d')]);
            $CurrentYearValue = $this->reportsService->GetAllBudgets(['date_init' => Carbon::parse($date_init)->startOfYear(), 'date_end' => $dtFim->format('Y-m-d')]);

            try {
                $goals = [
                    "date" => $dtFim->format('Y-m-d'),
                    "mes" => [
                        "porcentagemReais" => (($CurrentMonthValue->sum * 100) / $monthGoal->value) > 100 ? 100 : (($CurrentMonthValue->sum * 100) / $monthGoal->value),
                        "atualReais" => $CurrentMonthValue->sum == null ? 0 : $CurrentMonthValue->sum,
                        "metaReais" =>  $monthGoal->value,

                        "porcentagemJobs" => (($allMes->count * 100) / $monthGoal->expected_value) > 100 ? 100 : (($allMes->count * 100) / $monthGoal->expected_value),
                        "atualJobs" => $allMes->count,
                        "metaJobs" => $monthGoal->expected_value,
                    ],
                    "anual" => [
                        "porcentagemReais" => (($CurrentYearValue->sum * 100) / $yearGoals->value) > 100 ? 100 : (($CurrentYearValue->sum * 100) / $yearGoals->value),
                        "atualReais" =>  $CurrentYearValue->sum == null ? 0 : $CurrentYearValue->sum,
                        "metaReais" =>  $yearGoals->value,

                        "porcentagemJobs" => (($allAno->sum * 100) / $yearGoals->expected_value) > 100 ? 100 : (($allAno->sum * 100) / $yearGoals->expected_value),
                        "atualJobs" => $allAno->count,
                        "metaJobs" => $yearGoals->expected_value,
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
