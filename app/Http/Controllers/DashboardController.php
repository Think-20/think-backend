<?php

namespace App\Http\Controllers;

use App\Client;
use App\Job;
use App\JobActivity;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {

        return response()->json(
            [
                "alertas" => $this->CountAlerts(),
                "memorias" => $this->CountReminders(),
                "tempo_medio_aprovacao_dias" => [
                    "ref" => 38,
                    "total" => 45
                ],
                "intervalo_medio_aprovacao_dias" => [
                    "ref" => 13,
                    "total" => 7
                ],
                "ticket_medio_aprovacao" => [
                    "ref" => 190000,
                    "total" => 183000
                ],
                "maior_venda" => [
                    "ref" => 5338000,
                    "total" => 453000
                ],
                "tendencia_aprovacao_anual" => [
                    "ref" => 4.23108,
                    "total" => 4200000
                ],
                "media_aprovacao_mes" => [
                    "ref" => 400000,
                    "total" => 420000
                ],
                "ticket_medio_jobs" => [
                    "ref" => 170000,
                    "total" => 183000
                ],
                "ultimo_aprovado" => "Nestlé | Apas",
                "ultimo_job_aprovado" => "",
                "eventos_rolando" => "",
                "aniversariante" => "",
                "comunicados" => "",
                "metas" => "",
                "recordes" => "",
                "ranking" => [
                    "total" => 1
                ],
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
                    "meta_jobs" => 1200000,
                    "meta_aprovacao" => 400000,
                    "aprovados" => [
                        "total" => 7,
                        "porcentagem" => 20,
                        "valor" => 2000000
                    ],
                    "avancados" => [
                        "total" => 12,
                        "porcentagem" => 10,
                        "valor" => 2000000
                    ],
                    "ajustes" => [
                        "total" => 23,
                        "porcentagem" => 10,
                        "valor" => 2000000
                    ],
                    "stand_by" => [
                        "total" => 54,
                        "porcentagem" => 30,
                        "valor" => 2000000
                    ],
                    "reprovados" => [
                        "total" => 73,
                        "porcentagem" => 40,
                        "valor" => 2000000
                    ],
                    "metas" => [
                        "ultimos_doze_meses" => [
                            "atual" => 4950000,
                            "meta" => 4800000
                        ],
                        "mes" => [
                            "atual" => 280000,
                            "meta" => 4800000
                        ],
                        "quarter" => [
                            "atual" => 1300000,
                            "meta" => 1200000
                        ],
                        "anual" => [
                            "atual" => 3810000,
                            "meta" => 4800000
                        ]
                    ],
                    "em_producao" => [
                        "total" => 4,
                        "total_em_producao" => 500,
                        "jobs" => [
                            [
                                "total" => 100,
                                "valor" => 90
                            ],
                            [
                                "total" => 100,
                                "valor" => 80
                            ],
                            [
                                "total" => 100,
                                "valor" => 40
                            ],
                            [
                                "total" => 100,
                                "valor" => 35
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
                    "meta_jobs" => 1200000,
                    "meta_aprovacao" => 103
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
                            "name" => "High - 2013",
                            "data" => [
                                28,
                                29,
                                33,
                                30,
                                45,
                                68,
                                68,
                                43,
                                42,
                                55,
                                33,
                                33
                            ]
                        ],
                        [
                            "name" => "Low - 2013",
                            "data" => [
                                12,
                                20,
                                25,
                                60,
                                32,
                                20,
                                10,
                                50,
                                25,
                                33,
                                33,
                                33
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

    public static function CountAlerts(){
        $jobs = Job::where('attendance_id', User::logged()->employee->id)
        ->with('client')
        ->where('status_id', 1)
        ->whereYear('created_at', 2023)
        ->whereDate('created_at', '<=', Carbon::now()->subDays(15)->startOfDay())
        ->count();
        
        return $jobs;
    }

    public static function CountReminders(){
        $startDate = Carbon::now()->subYear()->startOfDay();
        $endDate = Carbon::now()->subYear()->endOfDay();

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
        ->with('job_activity','tasks')
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
