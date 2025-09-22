<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DateTime;
use DB;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB as FacadesDB;

class UserNotification extends Model
{
    public $timestamps = false;

    protected $table = 'user_notification';

    protected $fillable = [
        'received_date',
        'received',
        'notification_id',
        'user_id',
        'read',
        'read_date',
        'special',
        'special_message'
    ];


    public static function read(array $data)
    {
        DB::beginTransaction();

        try {
            foreach ($data['ids'] as $id) {
                $userNotification = UserNotification::find($id);
                $userNotification->update(['read' => 1, 'read_date' => (new DateTime())->format('Y-m-d H:i:s')]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function list()
    {
        $usersNotification = UserNotification::select('user_notification.*')
            ->with('notification', 'notification.type', 'notification.notifier')
            ->leftJoin('notification', 'notification.id', '=', 'user_notification.notification_id')
            ->where('user_notification.user_id', '=', User::logged()->id)
            ->orderBy('notification.date', 'desc')
            ->paginate(20);

        return [
            'pagination' => [
                'data' => $usersNotification,
            ],
            'updatedInfo' => UserNotification::updatedInfo()
        ];
    }

    public static function recents()
    {
        self::checkStandByPendencies(); //Cria as stand by pendentes
        self::checkNotificaClientesInativos(); //Cria as notificações para os clientes a masi de 30 dias sem novas oportunidades
        self::checkInativaClientesInformados(); //Inativa os clientes que foram informados do prazo de 15 dias e ainda sim n foram inativados
        
        //self::checkInativeClients(); //Tentativa antiga de inativar os clientes que não tem job

        dd("Chegou aqui");

        $usersNotification = UserNotification::select('user_notification.*')
            ->with(['notification', 'notification.type', 'notification.notifier'])
            ->leftJoin('notification', 'notification.id', '=', 'user_notification.notification_id')
            ->where('user_notification.user_id', '=', User::logged()->id)
            ->where('user_notification.received', '=', '1')
            ->orderBy('notification.date', 'desc')
            ->limit(60)
            ->get();

        return [
            'pagination' => [
                'data' => $usersNotification,
            ],
            'updatedInfo' => UserNotification::updatedInfo()
        ];
    }

    public static function listen()
    {
        $usersNotification = UserNotification::select('user_notification.*')
            ->with(['notification', 'notification.type', 'notification.notifier'])
            ->leftJoin('notification', 'notification.id', '=', 'user_notification.notification_id')
            ->where('user_notification.user_id', '=', User::logged()->id)
            ->where('received', '=', '0')
            ->orderBy('notification.date', 'desc')
            ->get();

        UserNotification::whereIn('id', $usersNotification->map(function ($u) {
            return $u->id;
        }))->update(['received' => 1, 'received_date' => (new DateTime())->format('Y-m-d H:i:s')]);

        return $usersNotification;
    }

    
    private static function checkStandByPendencies()
    {
        $jobs = Job::where('attendance_id', User::logged()->employee->id)
            ->with('client')
            ->where('status_id', 1)
            ->whereYear('created_at', '>=', 2023)
            ->whereDate('created_at', '<=', Carbon::now()->subDays(15)->startOfDay())
            ->get();

        if ($jobs->isEmpty()) {
            return;
        }

        foreach ($jobs as $job) {
            $message = 'Projeto ';

            if (isset($job->client)) {
                $message .= $job->client['name'];
            } elseif (isset($job->not_client)) {
                $message .= $job->not_client;
            }

            $message .= ' do evento ' . $job->event . ' em standby há mais de 15 dias.';

            $searchNotification = Notification::where('message', $message)->where('notifier_id', User::logged()->employee->id)->first();
            if (!$searchNotification) {
                $notification = new Notification();
                $notification->type_id = 18;
                $notification->notifier_id = User::logged()->employee->id;
                $notification->notifier_type = "App\Employee";
                $notification->info = "Id do job: " . $job->id;
                $notification->date = Carbon::now()->toDateTimeString();
                $notification->message = $message;
                $notification->save();

                $userNotification = new UserNotification();
                $userNotification->notification_id = $notification->id;
                $userNotification->user_id = User::logged()->id;
                $userNotification->special = 1;
                $userNotification->special_message = $message;
                $userNotification->received = 0;
                $userNotification->received_date = null;
                $userNotification->read = 0;
                $userNotification->read_date = null;
                $userNotification->save();
            }
        }
    }

    private static function  checkInativeClients()
    {

        //3 meses alerta, 4 meses inativa
        //Cria os alertas para os cleintes do tipo agency quando já estão a mais de 3 meses sem job
        $agencyClients = FacadesDB::select(FacadesDB::raw("SELECT c.id,c.name, j1.created_at FROM client as c 
        JOIN job as j1 ON j1.client_id = c.id AND j1.created_at = (SELECT MAX(j.created_at) FROM job as j WHERE j.client_id = c.id )
        WHERE YEAR(j1.created_at) >= 2023
        AND j1.attendance_id = " . User::logged()->employee->id . " AND j1.created_at <=  DATE_SUB(NOW(), INTERVAL (SELECT notification_time FROM inactive_time WHERE type='agency') month)
        AND c.client_type_id = 1
        ORDER BY j1.created_at DESC"));

        //Inativa os clientes do tipo agency quando ja esta a mais de 4 meses sem job
        FacadesDB::select(FacadesDB::raw("UPDATE client as c 
        JOIN job as j1 ON j1.client_id = c.id 
        AND j1.created_at = (SELECT MAX(j.created_at) FROM job as j WHERE j.client_id = c.id )
        SET client_status_id = 1
        WHERE j1.attendance_id IS NOT NULL
        AND j1.attendance_id = " . User::logged()->employee->id . " AND j1.created_at <=  DATE_SUB(NOW(), INTERVAL (SELECT inactive_time FROM inactive_time WHERE type='agency') month)
        AND c.client_type_id = 1"));


        //6 meses alerta, 9 meses inativa
        //Cria os alertas para os cleintes do tipo exhibitor quando já estão a mais de 6 meses sem job
        $exhibitorClients = FacadesDB::select(FacadesDB::raw("SELECT c.id,c.name, j1.created_at FROM client as c 
        JOIN job as j1 ON j1.client_id = c.id AND j1.created_at = (SELECT MAX(j.created_at) FROM job as j WHERE j.client_id = c.id )
        WHERE YEAR(j1.created_at) >= 2023
        AND j1.attendance_id = " . User::logged()->employee->id  . " AND j1.created_at <=  DATE_SUB(NOW(), INTERVAL (SELECT notification_time FROM inactive_time WHERE type='expositor') month)
        AND c.client_type_id = 2
        ORDER BY j1.created_at DESC"));

        //Inativa os clientes do tipo exhibitor quando já estão a mais de 9 meses sem job
        FacadesDB::select(FacadesDB::raw("UPDATE client as c 
        JOIN job as j1 ON j1.client_id = c.id 
        AND j1.created_at = (SELECT MAX(j.created_at) FROM job as j WHERE j.client_id = c.id )
        SET client_status_id = 1
        WHERE j1.attendance_id IS NOT NULL
        AND j1.attendance_id = " . User::logged()->employee->id . " AND j1.created_at <=  DATE_SUB(NOW(), INTERVAL (SELECT inactive_time FROM inactive_time WHERE type='expositor') month)
        AND c.client_type_id = 2"));

        //Inativa os clientes que nunca estiveram em nenhum job
        //FacadesDB::select(FacadesDB::raw("UPDATE client as c SET client_status_id = 1 WHERE c.id NOT IN (SELECT client_id FROM job WHERE client_id IS NOT NULL GROUP BY client_id);"));

        if (!isset($agencyClients[0]) && !isset($exhibitorClients[0])) {
            return;
        }

        foreach ($agencyClients as $job) {

            $message = "Cliente '" . $job->name . "' do tipo agência";

            $message .= " a mais de 3 meses sem Jobs.";

            $searchNotification = Notification::where('message', $message)->where('notifier_id', User::logged()->employee->id)->first();
            if (!$searchNotification) {
                $notification = new Notification();
                $notification->type_id = 14;
                $notification->notifier_id = User::logged()->employee->id;
                $notification->notifier_type = "App\Employee";
                $notification->info = "";
                $notification->date = Carbon::now()->toDateTimeString();
                $notification->message = $message;
                $notification->save();

                $userNotification = new UserNotification();
                $userNotification->notification_id = $notification->id;
                $userNotification->user_id = User::logged()->id;
                $userNotification->special = 1;
                $userNotification->special_message = $message;
                $userNotification->received = 0;
                $userNotification->received_date = null;
                $userNotification->read = 0;
                $userNotification->read_date = null;
                $userNotification->save();
            }
        }

        foreach ($exhibitorClients as $job) {


            $message = "Cliente '" . $job->name . "' do tipo Expositor";

            $message .= " a mais de 6 meses sem Jobs.";

            $searchNotification = Notification::where('message', $message)->where('notifier_id', User::logged()->employee->id)->first();
            if (!$searchNotification) {
                $notification = new Notification();
                $notification->type_id = 14;
                $notification->notifier_id = User::logged()->employee->id;
                $notification->notifier_type = "App\Employee";
                $notification->info = "";
                $notification->date = Carbon::now()->toDateTimeString();
                $notification->message = $message;
                $notification->save();

                $userNotification = new UserNotification();
                $userNotification->notification_id = $notification->id;
                $userNotification->user_id = User::logged()->id;
                $userNotification->special = 1;
                $userNotification->special_message = $message;
                $userNotification->received = 0;
                $userNotification->received_date = null;
                $userNotification->read = 0;
                $userNotification->read_date = null;
                $userNotification->save();
            }
        }
    }

    private static function  checkNotificaClientesInativos()
    {
        $clients = FacadesDB::select(FacadesDB::raw("
            SELECT 
                c.id as clientId,    
                c.name as clientName,     
                j.updated_at
            FROM client as c
            JOIN employee as e ON e.id = c.employee_id
            JOIN job as j ON j.client_id = c.id
            WHERE e.id = " . User::logged()->id . "
            AND j.updated_at = (
                SELECT MAX(j2.updated_at) 
                FROM job as j2 
                WHERE j2.client_id = c.id
            )
            AND j.updated_at <= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            ORDER BY c.name ASC
        "));
        
        #dd();
        dd($clients);

        if (empty($clients)) {
            return;
        }

        foreach ($clients as $client) {
            $message = "O cliente " . $client->clientName . " já esta a mais de 3 meses sem nenhuma nova oportunidade,
                 caso nenhuma oportunidade seja criada com ele nos proximos 15 dias ele será inativado.";
            
            $searchNotification = Notification::where('message', $message)->where('notifier_id', User::logged()->employee->id)->first();
            if (!$searchNotification) {
                $notification = new Notification();
                $notification->type_id = 2;
                $notification->notifier_id = User::logged()->employee->id;
                $notification->notifier_type = "App\Employee";
                $notification->info = $client->clientId;
                $notification->date = Carbon::now()->toDateTimeString();
                $notification->message = $message;
                $notification->save();

                $userNotification = new UserNotification();
                $userNotification->notification_id = $notification->id;
                $userNotification->user_id = User::logged()->id;
                $userNotification->special = 1;
                $userNotification->special_message = $message;
                $userNotification->received = 0;
                $userNotification->received_date = null;
                $userNotification->read = 0;
                $userNotification->read_date = null;
                $userNotification->save();
            }
        }
    }

    private static function checkInativaClientesInformados()
    {
        // Busca notificações criadas há mais de 15 dias que foram geradas pela função checkNotificaClientesInativos
        $notifications = Notification::where('type_id', 2)
            ->where('notifier_id', User::logged()->employee->id)
            ->where('notifier_type', 'App\Employee')
            ->where('date', '<=', Carbon::now()->subDays(15)->toDateTimeString())
            ->where('message', 'like', 'Cliente % já esta a mais de 3 meses sem nenhuma nova oportunidade%')
            ->get();

        if ($notifications->isEmpty()) {
            return;
        }

        foreach ($notifications as $notification) {
            $clientId = $notification->info;
            
            // Remove a notificação e a userNoficiation relacionada a ela
            $notification->delete();
            UserNotification::where('notification_id', $notification->id)->delete();
            
            FacadesDB::table('client')
                ->where('id', $clientId)
                ->update(['client_status_id' => 1]);
            
            // Busca o nome do cliente para a notificação
            $client = FacadesDB::table('client')->where('id', $clientId)->first();
            $clientName = $client ? $client->name : 'Cliente desconhecido';
            
            // Busca todos os employees para notificar
            $employees = FacadesDB::table('employee')
                ->join('user', 'user.employee_id', '=', 'employee.id')
                ->where('user.active', 1)
                ->get();
            
            // Cria notificação para todos os employees
            $message = "O cliente '" . $clientName . "' está disponível para novos projetos";
            
            foreach ($employees as $employee) {
                // Verifica se já existe uma notificação similar para este employee
                $existingNotification = Notification::where('message', $message)
                    ->where('notifier_id', $employee->id)
                    ->where('notifier_type', 'App\Employee')
                    ->first();
                
                if (!$existingNotification) {
                    $newNotification = new Notification();
                    $newNotification->type_id = 18;
                    $newNotification->notifier_id = $employee->id;
                    $newNotification->notifier_type = "App\Employee";
                    $newNotification->info = $clientId;
                    $newNotification->date = Carbon::now()->toDateTimeString();
                    $newNotification->message = $message;
                    $newNotification->save();

                    $userNotification = new UserNotification();
                    $userNotification->notification_id = $newNotification->id;
                    $userNotification->user_id = $employee->user_id;
                    $userNotification->special = 1;
                    $userNotification->special_message = $message;
                    $userNotification->received = 0;
                    $userNotification->received_date = null;
                    $userNotification->read = 0;
                    $userNotification->read_date = null;
                    $userNotification->save();
                }
            }
        }
    }

    public static function updatedInfo()
    {
        $lastData = UserNotification::where('user_id', '=', User::logged()->id)
            ->orderBy('id', 'desc')->limit(1)->first();

        if ($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->notification->date))->format('d/m/Y'),
            'by' => $lastData->notification->notifier->getName()
        ];
    }

    public function notification()
    {
        return $this->belongsTo('App\Notification', 'notification_id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    //Função que vai trazer todos os dados para a tela de notificações que será exibida nas sextas-feiras
    public static function notificationsWindow()
    {
        $jobs = Job::where('attendance_id', User::logged()->employee->id)
            ->where('status_id', 1)
            ->whereYear('created_at', '>=', 2023)
            ->whereDate('created_at', '<=', Carbon::now()->subDays(15)->startOfDay())
            ->whereDate('status_updated_at', '<=', Carbon::now()->subDays(15)->startOfDay())
            ->with('job_activity', 'job_type', 'client', 'main_expectation', 'levels', 'how_come', 'agency', 'attendance', 'competition', 'files', 'status', 'creation')
            ->get();


        $count = Job::where('attendance_id', User::logged()->employee->id)
            ->where('status_id', 1)
            ->whereYear('created_at', '>=', 2023)
            ->whereDate('created_at', '<=', Carbon::now()->subDays(15)->startOfDay())
            ->whereDate('status_updated_at', '<=', Carbon::now()->subDays(15)->startOfDay())
            ->count();

        $jobsResult = [];
        foreach ($jobs as $job) {
            $lastUpdateDate = Carbon::parse($job['status_updated_at']);
            $atualDate = Carbon::now();
            $diferencaDias = $atualDate->diffInDays($lastUpdateDate);

            if ($job['client'] != null) {
                $client = $job['client']['fantasy_name'];
            } else if ($job['not_client'] != null) {
                $client = $job["not_client"];
            }

            if (isset($job['creation'][0]['responsible_id'])) {
                $responsible = $job['creation'][0]['responsible']['name'];
            }

            array_push($jobsResult, [
                "id" => $job['id'],
                "code" => $job['code'],
                "days_without_update" => $diferencaDias,
                "job_activity" => $job['job_activity']['description'],
                "job_type" => $job['job_type']['description'],
                "client" => $client ?? null,
                "event" => $job['event'],
                "deadline" => $job['deadline'],
                "creation_responsible" => $responsible ?? null,
                "budget_value" => $job['budget_value'],
                "attendance" => isset($job['attendance']['name']) ? $job['attendance']['name'] : null,
                "area" => isset($job['area']) ? $job['area'] : null,
                "status" => $job['status']['description']
            ]);
        }

        return response()->json([
            "update_pendency" => [
                "count" => $count,
                "data" => $jobsResult
            ]
        ]);
    }

    //Função que vai trazer todos os dados para a tela de notificações de checkin que será exibida para os responsaveis de cada área
    public static function notificationsWindowCheckin()
    {

        $loggedId = User::logged()->employee->id;

        //Função desbloqueada apenas para usuários especificos que terão acesso a alterar aceites de checkin sendo:
        /*
            Victor Goularte de id 43
            Gleice Alves de id 76
        */


        if ($loggedId == 76 || $loggedId == 43) {
            //Variavel que ira auxiliar na contrução da busca separada por tipo de usuario verificando o tipo de aceite dele
            $aux = "";

            switch ($loggedId) {
                case 76:
                    $aux = ' AND board_approval IS NULL;';
                    $checkins = Checkin::where('approval', true)->where('board_approval', null)->get();
                    break;

                //Era o Ivanildo na think
                case 20:
                    $aux = ' AND board_approval = true AND accept_proposal IS NULL;';

                    $checkins = Checkin::where('approval', true)->where('board_approval', true)->where('accept_proposal', null)->get();
                    break;

                case 76  || 43:
                    $aux = ' AND board_approval = true AND accept_proposal = true AND accept_production IS NULL;';
                    $checkins = Checkin::where('approval', true)->where('board_approval', true)->where('accept_proposal', true)->where('accept_production', null)->get();
                    break;

                default:
                    return response()->json([
                        "error" =>  true,
                        "message" => "Usuário logado não tem permissão para adicionar aceites em checkin."
                    ]);
                    break;
            }

            $count = FacadesDB::select(FacadesDB::raw("SELECT COUNT(id) as count FROM checkin WHERE approval = true" . $aux))[0]->count;

            $jobsResult = [];
            foreach ($checkins as $checkin) {

                $checkin->job;
                $checkin->job->client;

                $lastUpdateDate = Carbon::parse($checkin['updated_at']);
                $atualDate = Carbon::now();
                $diferencaDias = $atualDate->diffInDays($lastUpdateDate);


                if ($checkin['job']['client'] != null) {
                    $client = $checkin['job']['client']['fantasy_name'];
                } else if ($checkin['job']['not_client'] != null) {
                    $client = $checkin['job']["not_client"];
                }

                if (isset($checkin['job']['creation'][0]['responsible_id'])) {
                    $responsible = $checkin['job']['creation'][0]['responsible']['name'];
                }

                array_push($jobsResult, [
                    "id" => $checkin['job']['id'],
                    "code" => $checkin['job']['code'],
                    "days_without_update" => $diferencaDias,
                    "job_activity" => $checkin['job']['job_activity']['description'],
                    "job_type" => $checkin['job']['job_type']['description'],
                    "client" => $client ?? null,
                    "event" => $checkin['job']['event'],
                    "deadline" => $checkin['job']['deadline'],
                    "creation_responsible" => $responsible ?? null,
                    "budget_value" => $checkin['job']['budget_value'],
                    "attendance" => isset($checkin['job']['attendance']['name']) ? $checkin['job']['attendance']['name'] : null,
                    "area" => isset($checkin['job']['area']) ? $checkin['job']['area'] : null,
                    "status" => $checkin['job']['status']['description']
                ]);
            }

            return response()->json([
                "update_pendency" => [
                    "count" => $count,
                    "data" => $jobsResult
                ]
            ]);
        } else {
            return response()->json([
                "error" =>  true,
                "message" => "Usuário logado não tem permissão para adicionar aceites em checkin."
            ]);
        }
    }
}
