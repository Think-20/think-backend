<?php

namespace App\Http\Controllers;

use App\Client;
use App\Job;
use App\JobActivity;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Service\ReportsService;

class DashboardController extends Controller
{
    private $reportsService;
    public function __construct(ReportsService $reportsService)
    {
        $this->reportsService = $reportsService;
    }

    public function index(Request $request)
    {
        $dtInicio = isset($request->date_init) ? Carbon::parse($request->date_init) : Carbon::now()->startOfYear();
        $dtFim = isset($request->date_end) ? Carbon::parse($request->date_end) : Carbon::now()->endOfMonth();
        $request["userFilter"] = false;

        $aprovados = $this->reportsService->GetApproveds(["date_init" => $dtInicio, "date_end" => $dtFim]);
        $avancados = $this->reportsService->GetAdvanceds(["date_init" => $dtInicio, "date_end" => $dtFim]);
        $standby = $this->reportsService->GetStandbys(["date_init" => $dtInicio, "date_end" => $dtFim]);
        $reprovados = $this->reportsService->GetReproveds(["date_init" => $dtInicio, "date_end" => $dtFim]);
        $ajustes = $this->reportsService->GetAdjusts(["date_init" => $dtInicio, "date_end" => $dtFim]);

        $jobsByCategories = $this->reportsService->GetByCategories(["date_init" => $dtInicio, "date_end" => $dtFim]);
        $soma = $aprovados->count + $avancados->count + $standby->count + $reprovados->count; // Aqui não entra o count do ajustes porque o ajustes tbm são jobs em standby
        $somaAmount = $aprovados->amount + $avancados->amount + $standby->amount + $reprovados->amount + $ajustes->amount;

        $listaAprovados = $this->reportsService->GetLastApproveds(["date_init" => $dtInicio, "date_end" => $dtFim]);

        return response()->json(
            [
                "alertas" => $this->CountAlerts($dtInicio, $dtFim),
                "memorias" => $this->CountReminders($dtInicio, $dtFim),
                "tempo_medio_aprovacao_dias" => [
                    "total" => $this->reportsService->sumTimeToAproval($request->all()),
                ],
                "intervalo_medio_aprovacao_dias" => [
                    "total" => 7
                ],
                "ticket_medio_aprovacao" => [
                    "total" => $this->reportsService->averageTicket($request->all())
                ],
                "maior_venda" => [
                    "total" => $this->reportsService->BiggestSale($request->all())
                ],
                "tendencia_aprovacao_anual" => [
                    "total" => $this->reportsService->averageApprovedJobsPerMonth($request->all())['valueNumber'] * 12
                ],
                "media_aprovacao_mes" => [
                    "total" => $this->reportsService->averageApprovedJobsPerMonth($request->all())['valueNumber']
                ],
                "ticket_medio_jobs" => [
                    "total" => $this->reportsService->averageTicket($request->all())
                ],
                "ultimo_aprovado" => $this->reportsService->LastJobApproved(),
                "ultimo_job_aprovado" => $this->reportsService->LastJobApproved(),
                "eventos_rolando" => "",
                "aniversariante" => "",
                "comunicados" => "",
                "metas" => "",
                "recordes" => "",
                "ranking" => $this->reportsService->SaleRanking(),
                "jobs" => [
                    "labels" => [
                        "Aprovados",
                        "Avançados",
                        "Ajustes",
                        "Stand-By",
                        "Reprovados"
                    ],
                    "colors" => [
                        "#adca5f",
                        "#e82489",
                        "#4fa2b1",
                        "#00abeb",
                        "#ffcd37"
                    ],
                    "series" => [
                        $aprovados->count,
                        $avancados->count,
                        $ajustes->count,
                        $standby->count - $ajustes->count, // Aqui é retirado o count do ajustes do stand-by porque o ajustes tbm são jobs em standby
                        $reprovados->count
                    ],
                    "meta_jobs" => 1200000,
                    "meta_aprovacao" => 400000,
                    "total" => $soma,
                    "aprovados" => [
                        "total" => $aprovados->count,
                        "porcentagem" => round(($aprovados->count * 100) / $soma, 2),
                        "valor" => $aprovados->sum
                    ],
                    "avancados" => [
                        "total" => $avancados->count,
                        "porcentagem" => round(($avancados->count * 100) / $soma, 2),
                        "valor" => $avancados->sum
                    ],
                    "ajustes" => [
                        "total" => $ajustes->count,
                        "porcentagem" => round(($ajustes->count * 100) / $soma, 2),
                        "valor" => $ajustes->sum
                    ],
                    "stand_by" => [
                        "total" => $standby->count - $ajustes->count, // Aqui é retirado o count do ajustes do stand-by porque o ajustes tbm são jobs em standby
                        "porcentagem" => round(($standby->count * 100) / $soma, 2),
                        "valor" => $standby->sum
                    ],
                    "reprovados" => [
                        "total" => $reprovados->count,
                        "porcentagem" => round(($reprovados->count * 100) / $soma, 2),
                        "valor" => $reprovados->sum
                    ],
                    "metas" => $this->reportsService->GetGoals(),
                    "em_producao" => [
                        "total" => 4,
                        "total_em_producao" => $listaAprovados->count(),
                        "jobs" => [
                            [
                                "total" => $listaAprovados[0]['final_value'],
                                "valor" => $listaAprovados[0]['budget_value'],
                                "nome" => $listaAprovados[0]->getJobName()
                            ],
                            [
                                "total" => $listaAprovados[1]['final_value'],
                                "valor" => $listaAprovados[1]['budget_value'],
                                "nome" => $listaAprovados[1]->getJobName()
                            ],
                            [
                                "total" => $listaAprovados[2]['final_value'],
                                "valor" => $listaAprovados[2]['budget_value'],
                                "nome" => $listaAprovados[2]->getJobName()
                            ],
                            [
                                "total" => $listaAprovados[3]['final_value'],
                                "valor" => $listaAprovados[3]['budget_value'],
                                "nome" => $listaAprovados[3]->getJobName()
                            ]
                        ]
                    ],
                    "prazo_final" => [
                        "total" => 5,
                        "valor" => 7000000
                    ]
                ],
                "jobs2" => [
                    "labels" => [
                        "Cenografia",
                        "Stand",
                        "PDV",
                        "Showrooms",
                        "Outsiders"
                    ],
                    "colors" => [
                        "#adca5f",
                        "#e82489",
                        "#4fa2b1",
                        "#00abeb",
                        "#ffcd37"
                    ],
                    "series" => [
                        $jobsByCategories['Cenografia']["count"] ?? 0,
                        $jobsByCategories['Stand']["count"] ?? 0,
                        $jobsByCategories['PDV']["count"] ?? 0,
                        $jobsByCategories['Showroom']["count"] ?? 0,
                        $jobsByCategories['Outsiders']["count"] ?? 0,
                    ],
                    "meta_jobs" => $jobsByCategories['totals']['totalSum'],
                    "total" => $jobsByCategories['totals']['totalCount'],
                    "meta_aprovacao" => 400000,
                ],
                "tendencia" => [
                    "meses_ano" => [
                        "Jan 23",
                        "Fev 23",
                        "Mar 23",
                        "Abr 23",
                        "Mai 23",
                        "Jun 23",
                        "Jul 23",
                        "Ago 23",
                        "Set 23",
                        "Out 23",
                        "Nov 23",
                        "Dez 23"
                    ],
                    "series" => [
                        [
                            "name" => "Meta",
                            "data" => [
                                $this->reportsService->getGoal(1),
                                $this->reportsService->getGoal(2),
                                $this->reportsService->getGoal(3),
                                $this->reportsService->getGoal(4),
                                $this->reportsService->getGoal(5),
                                $this->reportsService->getGoal(6),
                                $this->reportsService->getGoal(7),
                                $this->reportsService->getGoal(8),
                                $this->reportsService->getGoal(9),
                                $this->reportsService->getGoal(10),
                                $this->reportsService->getGoal(11),
                                $this->reportsService->getGoal(12)
                            ]
                        ],
                        [
                            "name" => "Realizado",
                            "data" => [
                                120000,
                                200000,
                                250000,
                                600000,
                                320000,
                                200000,
                                100000,
                                500000,
                                250000,
                                330000,
                                330000,
                                300003
                            ]
                        ]
                    ],
                    "colors" => [
                        "#77B6EA",
                        "#545454"
                    ],
                    "meta_mensal" => 400000
                ],
                "sold_out" => [
                    "total" => 1,
                    "valor" => 2000000
                ]
            ]
        );
    }

    public static function CountAlerts($inicio, $fim)
    {

        $jobs = Job::where('attendance_id', User::logged()->employee->id)
            ->with('client')
            ->where('status_id', 1)
            ->whereDate('created_at', '>=', $inicio->subYear())
            ->whereDate('created_at', '<=', $fim->subYear())
            ->count();

        return $jobs;
    }

    public static function CountReminders($startDate, $endDate)
    {

        $OneYearClientRegister = Client::where('employee_id', User::logged()->employee->id)
            ->with('type', 'status')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->count();

        $OneYearJobCreation = Job::selectRaw('job.*')
            ->where('attendance_id', User::logged()->employee->id)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->count();

        $OneYearJobApproved = Job::selectRaw('job.*')
            ->with('job_activity', 'tasks')
            ->where(function ($query) {
                $query->where('attendance_id', User::logged()->employee->id)
                    ->orWhereHas('tasks', function ($query) {
                        $query->where('responsible_id', User::logged()->employee->id)
                            ->where('job_activity_id', JobActivity::where('description', 'Projeto')
                                ->first()->id);
                    });
            })
            ->where('status_id', 3)
            ->whereDate('status_updated_at', '>=', $startDate)
            ->whereDate('status_updated_at', '<=', $endDate)
            ->count();
        return $OneYearClientRegister + $OneYearJobCreation + $OneYearJobApproved;
    }
}
