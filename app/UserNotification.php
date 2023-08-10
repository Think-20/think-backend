<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DateTime;
use DB;
use Illuminate\Support\Carbon;

class UserNotification extends Model
{
    public $timestamps = false;

    protected $table = 'user_notification';

    protected $fillable = [
        'received_date', 'received', 'notification_id', 'user_id', 'read', 'read_date',
        'special', 'special_message'
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
        self::checkStandByPendencies();
        
        $usersNotification = UserNotification::select('user_notification.*')
            ->with(['notification', 'notification.type', 'notification.notifier'])
            ->leftJoin('notification', 'notification.id', '=', 'user_notification.notification_id')
            ->where('user_notification.user_id', '=', User::logged()->id)
            ->where('received', '=', '1')
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
        }))
            ->update(['received' => 1, 'received_date' => (new DateTime())->format('Y-m-d H:i:s')]);



        return $usersNotification;
    }

    private static function checkStandByPendencies()
    {
        $jobs = Job::where('attendance_id', User::logged()->employee->id)
            ->with('client')
            ->where('status_id', 1)
            ->whereYear('created_at', 2023)
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
            ->whereYear('created_at', 2023)
            ->whereDate('created_at', '<=', Carbon::now()->subDays(15)->startOfDay())
            ->with('job_activity', 'job_type', 'client', 'main_expectation', 'levels', 'how_come', 'agency', 'attendance', 'competition', 'files', 'status', 'creation')
            ->get();

        $count = Job::where('attendance_id', User::logged()->employee->id)
            ->where('status_id', 1)
            ->whereYear('created_at', 2023)
            ->whereDate('created_at', '<=', Carbon::now()->subDays(15)->startOfDay())
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
                "client" => $client,
                "event" => $job['event'],
                "deadline" => $job['deadline'],
                "creation_responsible" => $responsible ?? null,
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
}
