<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DB;
use DateTime;
use DateInterval;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Job extends Model
{
    public $timestamps = true;

    protected $table = 'job';

    protected $fillable = [
        'code',
        'job_activity_id',
        'client_id',
        'event',
        'deadline',
        'job_type_id',
        'agency_id',
        'attendance_id',
        'rate',
        'competition_id',
        'last_provider',
        'not_client',
        'how_come_id',
        'approval_expectation_rate',
        'main_expectation_id',
        'budget_value',
        'status_id',
        'note',
        'place',
        'area',
        'moments',
        'created_at',
        'time_to_aproval',
        'attendance_comission_id',
        'comission_percentage',
        'created_at',
        'updated_at',
        'final_value',
        'feedback_user_name',
        'feedback_user_email',
        'feedback_user_phone',
        'feedback_hash'
    ];

    protected $dates = [
        'created_at',
        'updated_at'
    ];

    public function getJobName()
    {
        $name = ($this->client ? $this->client->fantasy_name : $this->not_client);
        $event = $this->event;

        return $name . ' | ' . $event;
    }

    public static function loadForm()
    {
        return [
            'job_activities' => JobActivity::list(),
            'job_types' => JobType::all(),
            'attendances' => Employee::canInsertClients(),
            'competitions' => JobCompetition::all(),
            'main_expectations' => JobMainExpectation::all(),
            'levels' => JobLevel::all(),
            'how_comes' => JobHowCome::all(),
            'status' => JobStatus::all(),
        ];
    }

    public static function edit(array $data)
    {
        $id = $data['id'];
        $job = Job::find($id);
        $oldJob = clone $job;

        isset($data['agency']['id']) ? $job->agency_id = $data['agency']['id'] : $job->agency_id = $job->agency_id;
        isset($data['client']['id']) ? $job->client_id = $data['client']['id'] : $job->client_id = $job->client_id;

        isset($data['main_expectation']['id']) ? $job->main_expectation_id = $data['main_expectation']['id'] : null;
        isset($data['job_activity']['id']) ? $job->job_activity_id = $data['job_activity']['id'] : null;
        isset($data['status']['id']) ? $job->status_id = $data['status']['id'] : null;
        isset($data['how_come']['id']) ? $job->how_come_id = $data['how_come']['id'] : null;
        isset($data['attendance']['id']) ? $job->attendance_id = $data['attendance']['id'] : null;
        isset($data['competition']['id']) ? $job->competition_id = $data['competition']['id'] : null;

        isset($data['event_id']) ? $job->event_id = $data['event_id'] : null;

        if (isset($data['comission'])) {
            $job->attendance_comission_id = $data['comission']['attendance']['id'];
            $job->comission_percentage = $data['comission']['percentage'];
        }

        if (isset($data['critical'])) {
            $job->critical = $data['critical'];
        }

        $job->save();
        $job->update($data);
        $job->statusChange($oldJob);

        $arrayLevels = !isset($data['levels']) ? [] : $data['levels'];
        $job->saveLevels($arrayLevels);

        $arrayFiles = !isset($data['files']) ? [] : $data['files'];
        $job->editFiles($arrayFiles);

        return $job;
    }

    public function statusChange(Job $oldJob)
    {
        if ($this->status->id == '3' || $this->status->id == '2' || $this->status->id == '4') {
            $difference = strtotime($oldJob->created_at) - strtotime((new DateTime())->format('y-m-d'));
            $days = floor($difference / (60 * 60 * 24));
            $this->time_to_aproval = abs($days);
        }

        if ($oldJob->status_id != $this->status_id) {
            $this->notifyStatusChange();
        }

        $this->status_updated_at = (new DateTime())->format('y-m-d');
        $this->update();
    }

    public static function calculate()
    {
        $jobs = Job::where('status_id', 3)->whereNull('time_to_aproval')->get();

        foreach ($jobs as $job) {
            $difference = strtotime($job->created_at) - strtotime($job->status_updated_at);
            $days = floor($difference / (60 * 60 * 24)) * -1;
            $job->time_to_aproval = $days;
            $job->save();
        }

        return response()->json(['success' => true, "message" => "Reprocessamento realizado"]);
    }

    public function notifyStatusChange()
    {
        if (isset($this->tasks[0])) {
            $task = $this->tasks[0];
            $message = $task->job_activity->description . ' de ';
            $message .= $this->getJobName();
            $message .= ' teve o status alterado para ' . $this->status->description;

            Notification::createAndNotify(
                User::logged()->employee,
                [
                    'message' => $message
                ],
                NotificationSpecial::createMulti([
                    'user_id' => $task->responsible->user->id,
                    'message' => $message,
                ], [
                    'user_id' => $this->attendance->user->id,
                    'message' => $message
                ]),
                'Alteração de job',
                $task->id
            );
        }
    }

    public static function insert(array $data)
    {
        Job::checkData($data);
        $code = Job::generateCode();
        $agency_id = isset($data['agency']['id']) ? $data['agency']['id'] : null;
        $client_id = isset($data['client']['id']) ? $data['client']['id'] : null;

        $job = new Job(
            array_merge($data, [
                'code' => $code,
                'job_activity_id' => $data['job_activity']['id'],
                'client_id' => $client_id,
                'main_expectation_id' => $data['main_expectation']['id'],
                'status_id' => $data['status']['id'],
                'how_come_id' => $data['how_come']['id'],
                'job_type_id' => $data['job_type']['id'],
                'agency_id' => $agency_id,
                'attendance_id' => $data['attendance']['id'],
                'competition_id' => $data['competition']['id'],
                //'critical' => $data['critical']
            ])
        );

        if (isset($data['comission'])) {
            $job->attendance_comission_id = $data['comission']['attendance']['id'];
            $job->comission_percentage = $data['comission']['percentage'];
        }

        if (isset($data['event_id'])) {
            $job->event_id = $data['event_id'];
        }

        if (isset($data['critical'])) {
            $job->critical = $data['critical'];
        }

        $job->save();

        $arrayLevels = !isset($data['levels']) ? [] : $data['levels'];
        $job->saveLevels($arrayLevels);

        $arrayFiles = !isset($data['files']) ? [] : $data['files'];
        $job->saveFiles($arrayFiles);

        return $job;
    }

    public static function downloadFile($id, $type, $file)
    {
        $job = Job::find($id);
        $user = User::logged();

        if (is_null($job)) {
            throw new \Exception('O job solicitado não existe.');
        }

        switch ($type) {
            case 'job': {
                    $path = env('FILES_FOLDER') . '/jobs/' . $job->id . '/' . $file;
                    break;
                }
            case 'stand': {
                    $path = env('FILES_FOLDER') . '/stands/' . $job->briefing->stand->id . '/' . $job->briefing->stand->{$file};
                    break;
                }
            default: {
                    throw new \Exception('O tipo de arquivo solicitado não existe. ' . $type);
                }
        }

        FileHelper::checkIfExists($path);
        return $path;
    }

    public static function remove($id)
    {
        $job = Job::find($id);
        $oldJob = clone $job;
        $job->levels()->detach();
        $createNotification = true;

        foreach ($job->tasks as $task) {
            Task::remove($task->id);
            $createNotification = false;
        }

        if ($createNotification && isset($task)) {
            $message = $task->job_activity->description . ' de ';
            $message .= $task->job->getJobName();
            $message .= ' removido';

            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message
            ], NotificationSpecial::createMulti([
                'user_id' => $task->responsible->user->id,
                'message' => $message,
            ], [
                'user_id' => $task->job->attendance->user->id,
                'message' => $message
            ]), 'Deleção de job', $task->id);
        }

        $job->deleteFiles();
        //$job->briefing ? $job->briefing->delete() : null;
        //$job->budget ? $job->budget->delete() : null;
        $job->delete();
    }

    public static function list()
    {
        //$jobs = Job::orderBy('available_date', 'asc')->paginate(20);

        $jobs = Job::with('tasks')->orderBy('available_date', 'asc')->paginate(20);

        foreach ($jobs as $job) {
            $job->agency;
            $job->responsibles();
            $job->job_activity;
            $job->job_type;
            $job->attendance;
            $job->client;
            $job->status;
            $job->checkin;
        }

        return [
            'pagination' => $jobs,
            'updatedInfo' => Job::updatedInfo()
        ];
    }

    public static function get(int $id)
    {
        $job = Job::find($id);


        $job->job_activity;
        $job->job_type;
        $job->client;

        if ($job->client)
            $job->client->contacts;

        $job->main_expectation;
        $job->levels;
        $job->how_come;
        $job->agency;

        if ($job->agency)
            $job->agency->contacts;

        $job->attendance;
        $job->competition;
        $job->files;
        $job->status;

        $job->checkin;

        $job->responsibles();
        $job->history();

        //valores da semaforização

        //Sempre verde ja que se esta buscando o job, quer dizer q a tela de informação ja foi preenchida
        $job->info_check = 2;

        //verifica se a aba de briefing esta preenchida
        $job->briefing_check = $job->briefingCheck($job);

        //Verifica se a aba de projeto esta preenchida
        if ($job->briefing_check  == 2) {
            $job->project_check = $job->projectCheck($job);
        } else {
            $job->project_check = null;
        }

        //Verifica se a aba de memorial descritivo esta preenchida
        if ($job->project_check  == 2) {
            $job->descriptive_memorial_check = $job->descriptiveMemorialCheck($job);
        } else {
            $job->descriptive_memorial_check = null;
        }

        //Verifica se a aba de orçamento esta preenchida
        if ($job->descriptive_memorial_check  == 2) {
            $job->budget_check = $job->budgetCheck($job);
        } else {
            $job->budget_check = null;
        }


        //Verifica se a aba de Checkin esta preenchida
        //Quando status do jogo aprovado -> vermelho
        //Algum campo preenchido ->amarelo
        if ($job->budget_check  == 2 && $job->status_id == 3) {
            $job->checkin_check = $job->checkinCheck($job);
        } else {
            $job->checkin_check = null;
        }

        //Verifica se a aba de Contrato e NF esta preenchida
        if ($job->checkin_check  == 2) {
            $job->contract_nf_check = $job->contractNfCheck($job);
        } else {
            $job->contract_nf_check = null;
        }

        //Verifica se a aba de fotos do projeto esta preenchida
        if ($job->contract_nf_check  == 2) {
            $job->project_photos_check = $job->projectPhotosCheck($job);
        } else {
            $job->project_photos_check = null;
        }

        //Verifica se a aba de Contrato e NF esta preenchida
        if ($job->project_photos_check  == 2) {
            $job->feedback_check = $job->feedbackCheck($job);
        } else {
            $job->feedback_check = null;
        }

        return $job;
    }

    public static function filter($params)
    {
        $iniDate = isset($params['iniDate']) ? $params['iniDate'] : null;
        $jobTypeId = isset($params['job_type']['id']) ? $params['job_type']['id'] : null;
        $jobActivities = isset($params['job_activities']) ? $params['job_activities'] : null;
        $jobActivitiesMode = isset($params['job_activities_mode']) ? $params['job_activities_mode'] : 'IN';
        $finDate = isset($params['finDate']) ? $params['finDate'] : null;
        $orderBy = isset($params['orderBy']) ? $params['orderBy'] : 'created_at';
        $initialDate = isset($params['initial_date']) ? substr($params['initial_date'], 0, 10) : null;
        $finalDate = isset($params['final_date']) ? substr($params['final_date'], 0, 10) : null;
        $status = isset($params['status']) ? $params['status'] : null;
        $clientName = isset($params['clientName']) ? $params['clientName'] : null;
        $attendanceId = isset($params['attendance']['id']) ? $params['attendance']['id'] : null;
        $creationId = isset($params['creation']['id']) ? $params['creation']['id'] : null;
        $paginate = isset($params['paginate']) ? $params['paginate'] : true;

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

        if (!is_null($status)) {
            $jobs->where('status_id', '=', $status);
        }

        if (!is_null($jobTypeId)) {
            $jobs->where('job_type_id', '=', $jobTypeId);
        }

        if (!is_null($jobActivities)) {
            $jobs->leftJoin('task', function ($query) use ($jobActivities) {
                $query->on('task.job_id', '=', 'job.id');
            });

            if ($jobActivitiesMode == 'IN') {
                $jobs->whereIn('task.job_activity_id', $jobActivities);
            } else {
                $jobs->whereNotIn('task.job_activity_id', $jobActivities);
            }

            $jobs->distinct('job.id');
        }

        if ($orderBy == 'created_at') {
            $jobs->orderBy('job.created_at', 'DESC');
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

        if ($paginate) {
            $paginate = $jobs->paginate(50);
            foreach ($paginate as $job) {
                $job->responsibles();
            }
            $page = $paginate->currentPage();
            $total = $paginate->total();

            return [
                'pagination' => $paginate,
                'updatedInfo' => Job::updatedInfo()
            ];
        } else {
            $result = $jobs->get();
            foreach ($result as $job) {
                $job->responsibles();
            }
            $total = $jobs->count();
            $page = 0;

            return [
                'pagination' => [
                    'data' => $result,
                    'total' => $total,
                    'page' => $page
                ],
                'updatedInfo' => Job::updatedInfo()
            ];
        }
    }


    #My Job#
    public static function editMyJob(array $data)
    {
        Job::checkData($data, true);

        $id = $data['id'];
        $job = Job::find($id);
        $oldJob = clone $job;
        $agency_id = isset($data['agency']['id']) ? $data['agency']['id'] : null;
        $client_id = isset($data['client']['id']) ? $data['client']['id'] : null;

        if ($job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para editar esse job.');
        }
        if (isset($data['comission'])) {
            $job->attendance_comission_id = $data['comission']['attendance']['id'];
            $job->comission_percentage = $data['comission']['percentage'];
        }

        $job->update(
            array_merge($data, [
                'job_activity_id' => $data['job_activity']['id'],
                'client_id' => $client_id,
                'agency_id' => $agency_id,
                'main_expectation_id' => $data['main_expectation']['id'],
                'status_id' => $data['status']['id'],
                'how_come_id' => $data['how_come']['id'],
                'attendance_id' => $data['attendance']['id'],
                'competition_id' => $data['competition']['id']
            ])
        );

        $job->statusChange($oldJob);

        $arrayLevels = !isset($data['levels']) ? [] : $data['levels'];
        $job->saveLevels($arrayLevels);

        $arrayFiles = !isset($data['files']) ? [] : $data['files'];
        $job->editFiles($arrayFiles);

        return $job;
    }

    public function saveLevels(array $data)
    {
        $this->levels()->detach();

        foreach ($data as $level) {
            $this->levels()->attach($level['id']);
        }
    }

    public static function downloadFileMyJob($id, $type, $file)
    {
        $job = Job::find($id);
        $user = User::logged();

        if ($job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para fazer downloads desse job.');
        }

        if (is_null($job)) {
            throw new \Exception('O job solicitado não existe.');
        }

        switch ($type) {
            case 'job': {
                    $path = env('FILES_FOLDER') . '/jobs/' . $job->id . '/' . $file;
                }
            case 'stand': {
                    $path = env('FILES_FOLDER') . '/stands/' . $job->stand->id . '/' . $job->stand->{$file};
                }
            default: {
                    throw new \Exception('O tipo de arquivo solicitado não existe. ' . $type);
                }
        }

        FileHelper::checkIfExists($path);

        return $path;
    }

    public static function removeMyJob($id)
    {
        $job = Job::find($id);

        if ($job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para remover esse job.');
        }

        $oldJob = clone $job;
        $job->levels()->detach();

        $createNotification = true;

        foreach ($job->tasks as $task) {
            Task::remove($task->id);
            $createNotification = false;
        }

        if ($createNotification && isset($task)) {
            $message = $task->job_activity->description . ' de ';
            $message .= $task->job->getJobName();
            $message .= ' removido';

            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message
            ], NotificationSpecial::createMulti([
                'user_id' => $task->responsible->user->id,
                'message' => $message,
            ], [
                'user_id' => $task->job->attendance->user->id,
                'message' => $message
            ]), 'Deleção de job', $task->id);
        }

        $job->deleteFiles();
        //$job->briefing ? $job->briefing->delete() : null;
        //$job->budget ? $job->budget->delete() : null;
        $job->delete();
    }

    public static function listMyJob()
    {
        $jobs = Job::with('tasks')->orderBy('available_date', 'asc')
            ->where('attendance_id', '=', User::logged()->employee->id)
            ->orWhere('task.responsible_id', '=', User::logged()->employee->id)
            ->paginate(20);

        foreach ($jobs as $job) {
            $job->agency;
            $job->responsibles();
            $job->job_activity;
            $job->job_type;
            $job->attendance;
            $job->client;
            $job->status;
        }

        return [
            'pagination' => $jobs,
            'updatedInfo' => Job::updatedInfo()
        ];
    }

    public static function getMyJob(int $id)
    {
        $job = Job::find($id);

        if ($job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para visualizar esse job.');
        }

        $job->job_activity;
        $job->job_type;
        $job->client;

        if ($job->client)
            $job->client->contacts;

        $job->main_expectation;
        $job->levels;
        $job->how_come;
        $job->agency;

        if ($job->agency)
            $job->agency->contacts;

        $job->attendance;
        $job->competition;
        $job->files;
        $job->status;
        $job->responsibles();
        $job->history();
        //$job->briefing ? $job->briefing->get() : null;
        //$job->budget ? $job->budget->get() : null;
        return $job;
    }

    public static function filterMyJob($params)
    {
        $iniDate = isset($params['iniDate']) ? $params['iniDate'] : null;
        $jobTypeId = isset($params['job_type']['id']) ? $params['job_type']['id'] : null;
        $jobActivities = isset($params['job_activities']) ? $params['job_activities'] : null;
        $jobActivitiesMode = isset($params['job_activities_mode']) ? $params['job_activities_mode'] : 'IN';
        $finDate = isset($params['finDate']) ? $params['finDate'] : null;
        $orderBy = isset($params['orderBy']) ? $params['orderBy'] : 'created_at';
        $status = isset($params['status']) ? $params['status'] : null;
        $clientName = isset($params['clientName']) ? $params['clientName'] : null;
        $attendanceId = isset($params['attendance']['id']) ? $params['attendance']['id'] : null;
        $creationId = isset($params['creation']['id']) ? $params['creation']['id'] : null;
        $paginate = isset($params['paginate']) ? $params['paginate'] : true;

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
                'creation',
                'tasks'
            );

        $jobs->whereHas('attendance', function ($query) {
            $query->where('id', '=', User::logged()->employee->id);
        });

        if (!is_null($clientName)) {
            $jobs->whereHas('client', function ($query) use ($clientName) {
                $query->where('fantasy_name', 'LIKE', '%' . $clientName . '%');
                $query->orWhere('name', 'LIKE', '%' . $clientName . '%');
            });
            $jobs->orWhere('not_client', 'LIKE', '%' . $clientName . '%');
        }

        if (!is_null($creationId)) {
            $jobs->whereHas('creation', function ($query) use ($creationId) {
                $query->where('responsible_id', '=', $creationId);
            });
        }

        if (!is_null($status)) {
            $jobs->where('status_id', '=', $status);
        }

        if (!is_null($jobTypeId)) {
            $jobs->where('job_type_id', '=', $jobTypeId);
        }

        if (!is_null($jobActivities)) {
            $jobs->leftJoin('task', function ($query) use ($jobActivities) {
                $query->on('task.job_id', '=', 'job.id');
            });
            if ($jobActivitiesMode == 'IN') {
                $jobs->whereIn('task.job_activity_id', $jobActivities);
            } else {
                $jobs->whereNotIn('task.job_activity_id', $jobActivities);
            }
            $jobs->distinct('job.id');
        }

        if ($orderBy == 'created_at') {
            $jobs->orderBy('job.created_at', 'DESC');
        }

        if ($paginate) {
            $paginate = $jobs->paginate(50);
            foreach ($paginate as $job) {
                $job->responsibles();
            }
            $page = $paginate->currentPage();
            $total = $paginate->total();

            return [
                'pagination' => $paginate,
                'updatedInfo' => Job::updatedInfo()
            ];
        } else {
            $result = $jobs->get();
            foreach ($result as $job) {
                $job->responsibles();
            }
            $total = $jobs->count();
            $page = 0;

            return [
                'pagination' => [
                    'data' => $result,
                    'total' => $total,
                    'page' => $page
                ],
                'updatedInfo' => Job::updatedInfo()
            ];
        }
    }

    public static function updatedInfo()
    {
        $lastData = Job::orderBy('updated_at', 'desc')->limit(1)->first();

        if ($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->updated_at))->format('d/m/Y'),
            'employee' => $lastData->attendance->name
        ];
    }

    public static function generateCode()
    {
        $result = DB::table('job')
            ->select(DB::raw('(MAX(code) + 1) as code'))
            ->where(DB::raw('YEAR(CURRENT_DATE())'), '=', DB::raw('YEAR(created_at)'))
            ->first();

        if ($result->code == null) {
            return 1;
        }

        return $result->code;
    }

    public function saveFiles(array $data)
    {
        $path = env('FILES_FOLDER') . '/jobs/' . $this->id;

        if (!is_dir($path)) {
            try {
                mkdir($path);
            } catch (Exception $e) {
                $sudoCommand = "sudo mkdir -p $path";
                shell_exec($sudoCommand);
            }
        }

        foreach ($data as $file) {
            rename(sys_get_temp_dir() . '/' .  $file['name'], $path . '/' . $file['name']);
            $this->files()->save(new JobFile([
                'job_id' => $this->id,
                'filename' => $file['name']
            ]));
        }
    }

    public function editFiles(array $data)
    {
        $browserFiles = [];
        $path = env('FILES_FOLDER') . '/jobs/' . $this->id;

        if (!is_dir($path)) {
            try {
                mkdir($path);
            } catch (Exception $e) {
                $sudoCommand = "sudo mkdir -p $path";
                shell_exec($sudoCommand);
            }
        }

        foreach ($data as $file) {
            $browserFiles[] = $file['name'];
            $oldFile = $this->files()
                ->where('job_file.filename', '=', $file['name'])
                ->first();

            if (is_file(sys_get_temp_dir() . '/' .  $file['name'])) {
                // Substituir / criar arquivo em caso de não existir
                rename(sys_get_temp_dir() . '/' .  $file['name'], $path . '/' . $file['name']);

                if (is_null($oldFile)) {
                    $this->files()->save(new JobFile([
                        'job_id' => $this->id,
                        'filename' => $file['name']
                    ]));
                }
            }
        }

        foreach ($this->files as $file) {
            try {
                if (!in_array($file->filename, $browserFiles)) {
                    unlink($path . '/' . $file->filename);
                    $file->delete();
                }
            } catch (\Exception $e) {
            }
        }
    }

    public function deleteFiles()
    {
        $path = env('FILES_FOLDER') . '/jobs/' . $this->id;
        foreach ($this->files as $file) {
            try {
                unlink($path . '/' . $file->filename);
                $file->delete();
            } catch (\Exception $e) {
            }
        }
    }

    public static function checkData(array $data, $editMode = false)
    {
        if (!isset($data['job_activity']['id'])) {
            throw new \Exception('Atividade do job não informado!');
        }

        if (!isset($data['status']['id'])) {
            throw new \Exception('Status não informado!');
        }

        if (!isset($data['main_expectation']['id'])) {
            throw new \Exception('Expectativa principal do job não informada!');
        }

        if (!isset($data['how_come']['id'])) {
            throw new \Exception('Motivo do job não informado!');
        }

        if (!isset($data['job_type']['id']) && !$editMode) {
            throw new \Exception('Tipo de job do job não informado!');
        }

        if (!isset($data['attendance']['id'])) {
            throw new \Exception('Atendimento do job não informado!');
        }

        if (!isset($data['client']['id']) && !isset($data['agency']['id'])) {
            throw new \Exception('Agencia/cliente do job não informado!');
        }

        if (!isset($data['client']['id']) && empty(trim($data['not_client']))) {
            throw new \Exception('Cliente do job não informado!');
        }

        if (!isset($data['competition']['id'])) {
            throw new \Exception('Concorrência do job não informada!');
        }
    }

    public function contractNfCheck($job)
    {
        $taskProject = Task::where('job_id', $job->id)->get();

        foreach ($taskProject as $task) {
            $contractNf = ContractNfFile::where('task_id', "=", $task['id'])->first();
            if ($contractNf) {
                return 2;
            }
        }
        
        return 1;
    }

    public function projectPhotosCheck($job)
    {
        $taskProject = Task::where('job_id', $job->id)->get();

        foreach ($taskProject as $task) {
            $contractNf = ProjectPhotos::where('task_id', "=", $task['id'])->first();
            if ($contractNf) {
                return 2;
            }
        }
        
        return 1;
    }

    public function feedbackCheck($job)
    {
        $taskProject = Task::where('job_id', $job->id)->get();

        foreach ($taskProject as $task) {
            $contractNf = ContractNfFile::where('task_id', "=", $task['id'])->first();
            if ($contractNf) {
                return 2;
            }
        }
        
        return 1;
    }

    public function briefingCheck($job)
    {
        $taskProject = Task::where('job_id', $job->id)->get();

        foreach ($taskProject as $task) {
            $briefing = BriefingFile::where('task_id', "=", $task['id'])->first();
            if ($briefing) {
                return 2;
            }
        }
        
        return 1;
    }
    

    //Começo das funções da semaforização
    public function projectCheck($job)
    {
        //job_activity de projeto é o de id 1
        $taskProject = Task::where('job_activity_id', 1)->where('job_id', $job->id)->first();
        if (!$taskProject) {
            return 1;
        }

        $projectFile = ProjectFile::where('task_id', "=", $taskProject->id)->first();

        if ($taskProject && $projectFile) {
            return 2;
        } else {
            return 1;
        }
    }

    public function descriptiveMemorialCheck($job)
    {
        //job_activity de Memorial descritivo é o de id 13
        $taskProject = Task::where('job_activity_id', 13)->where('job_id', $job->id)->first();
        $descriptiveMemorial = SpecificationFile::where('task_id', "=", $taskProject->id)->first();

        if ($taskProject && $descriptiveMemorial) {
            return 2;
        } else {
            return 1;
        }
    }

    public function budgetCheck($job)
    {
        //job_activity de orçamento é 2, mas aparentemente não esta atrelado a ele, então esta sendoo procurado um task com final_value
        $taskProject = Task::where('final_value', '>', 0)->where('job_id', $job->id)->first();

        if ($taskProject) {
            return 2;
        } else {
            return 1;
        }
    }

    public function checkinCheck($job)
    {
        $jobStatusApprove = Job::where('status_id', '=', 3)->where('id', $job->id)->first();

        if ($jobStatusApprove == null) {
            return 0;
        } else if ($jobStatusApprove) {
            return 1;
        } else {
            return 2;
        }
    }

    public function tasks()
    {
        return $this->hasMany('App\Task', 'job_id')->with(
            'project_files',
            'project_files.responsible',
            'briefing_files',
            'briefing_files.responsible',
            
            'contract_nf_files',
            'contract_nf_files.responsible',
            
            'specification_files',
            'specification_files.responsible',
            'job_activity.modification',
            'job_activity.option',
            'budget',
            'budget.responsible',
            'task',
            'task.job_activity',
            'responsible'
        )->orderBy('created_at', 'desc');
    }

    public function creation()
    {
        return $this->tasks()->whereIn('job_activity_id', [1, 11]);
    }

    public function attendance_responsible()
    {
        $this->attendance_responsible = $this->attendance;
    }

    public function creation_responsible()
    {
        foreach ($this->tasks as $task) {
            if ($task->job_activity->description == 'Projeto' || $task->job_activity->description == 'Outsider') {
                $this->creation_responsible = $task->responsible;
                $this->available_date = $task->getAvailableDate();
            }
        }
    }

    public function budget_responsible()
    {
        foreach ($this->tasks as $task) {
            if ($task->job_activity->description == 'Orçamento') {
                $this->budget_responsible = $task->responsible;
                $this->available_date = $task->getAvailableDate();
            }
        }
    }

    public function detailing_responsible()
    {
        foreach ($this->tasks as $task) {
            if ($task->job_activity->description == 'Detalhamento') {
                $this->detailing_responsible = $task->responsible;
            }
        }
    }

    public function production_responsible()
    {
        foreach ($this->tasks as $task) {
            if ($task->job_activity->description == 'Produção') {
                $this->production_responsible = $task->responsible;
            }
        }
    }

    public function responsibles()
    {
        $this->attendance_responsible();
        $this->creation_responsible();
        $this->budget_responsible();
        $this->detailing_responsible();
        $this->production_responsible();
    }

    public function history()
    {
        $job = $this;
        $jobs = Job::where(function ($query) use ($job) {
            $query->where('client_id', '=', $job->client_id);
        })->where(function ($query) use ($job) {
            $query->where('not_client', '=', $job->not_client);
            $query->where('agency_id', '=', $job->agency_id);
        })->get();

        $approved = $jobs->filter(function ($job) {
            return $job->status->description == 'Aprovado';
        })->count();
        $total = $jobs->count();
        $this->history = $approved . '/' . $total;
    }

    public static function performanceLite(array $data)
    {
        $month = isset($data['month']['id']) ? $data['month']['id'] : null;
        $year = isset($data['year']) ? $data['year'] : null;
        $time_to_analyze = isset($data['time_to_analyze']) ? $data['time_to_analyze'] : null;

        $firstDayMonth = (new DateTime('now'))->format('Y-m') . '-01';
        $lastDayMonth = (new DateTime('now'))->format('Y-m') . '-31';

        $firstDayYear = (new DateTime('now'))->format('Y') . '-01-01';
        $lastDayYear = (new DateTime('now'))->format('Y') . '-12-31';

        $opportunityQuery = Job::select(DB::raw('SUM(budget_value) as value, COUNT(id) as quantity'))
            ->whereIn('job_activity_id', JobActivity::getOpportunities()->map(function ($jA) {
                return $jA->id;
            }))
            ->where('created_at', '>=', $year . '-' . $month . '-01')
            ->where('created_at', '<=', $year . '-' . $month . '-31');

        $monthlyTendencyQuery = Job::select(DB::raw('COUNT(id) as quantity_total, SUM(status_id=3) as quantity_approved,
        SUM(budget_value) as budget_total, SUM(case when status_id=3 then budget_value else 0 end) as budget_approved'))
            ->whereIn('job_activity_id', JobActivity::getOpportunities()->map(function ($jA) {
                return $jA->id;
            }))
            ->where('created_at', '>=', DateHelper::subUtil(new DateTime, $time_to_analyze)->format('Y-m-d'));

        $monthlyApprovalQuery = Job::select(DB::raw('COUNT(id) as quantity_total, SUM(status_id=3) as quantity_approved,
        SUM(case when status_id=3 then budget_value else 0 end) as budget_approved'))
            ->whereIn('job_activity_id', JobActivity::getOpportunitiesAndOthers()->map(function ($jA) {
                return $jA->id;
            }))
            ->where('created_at', '>=', $firstDayMonth)
            ->where('created_at', '<=', $lastDayMonth);

        $consolidatedAnnualQuery = Job::select(DB::raw('COUNT(id) as quantity_total, SUM(status_id=3) as quantity_approved, 
        SUM(case when status_id=3 then budget_value else 0 end) as budget_approved'))
            ->whereIn('job_activity_id', JobActivity::getOpportunitiesAndOthers()->map(function ($jA) {
                return $jA->id;
            }))
            ->where('created_at', '>=', $firstDayYear)
            ->where('created_at', '<=', $lastDayYear);

        $opportunity = $opportunityQuery->first();
        $tendency = $monthlyTendencyQuery->first();
        $monthly_approval = $monthlyApprovalQuery->first();
        $consolidated_annual = $consolidatedAnnualQuery->first();

        $monthly_approval->quantity_approved = $monthly_approval->quantity_approved == null ? 0 : $monthly_approval->quantity_approved;
        $monthly_approval->budget_approved = $monthly_approval->budget_approved == null ? 0 : $monthly_approval->budget_approved;

        return [
            'opportunity' => $opportunity,
            'tendency' => $tendency,
            'monthly_approval' => $monthly_approval,
            'consolidated_annual' => $consolidated_annual
        ];
    }

    public function initialTask(): Task
    {
        return Task::with(['job_activity' => function ($query) {
            $query->where('initial', '1');
        }])
            ->where('job_id', $this->id)
            ->first();
    }

    /*public static function feedback($data)
    {
        $id = $data['job_id'];
        $job = Job::find($id);
        
        $job->feedback_hash = $data['feedback_hash'];
        $job->recommendation_rating = $data['recommendation_rating'];
        $job->overall_project_rating = $data['overall_project_rating'];
        $job->sales_support_rating = $data['sales_support_rating'];
        $job->project_feedback = $data['project_feedback'];
        
        $job->save();
        return $job;
    }*/

    public static function feedbackUpdate($data)
    {

        $id = $data['job_id'];
        $job = Job::find($id);

        if($job->feedback_hash == $data['feedback_hash']){
            
            $job->feedback_user_name = $data['feedback_user_name'];
            $job->feedback_user_email = $data['feedback_user_email'];
            $job->feedback_user_phone = $data['feedback_user_phone'];
            
            $job->save();
            return $job;
        }else{
            return "Job não encontrado!";
        }
    }

    public static function sendFeedbackEmail($data)
    {
        $host = 'smtp.gmail.com';
        $port = 587;
        $timeout = 10;

        $connection = fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!$connection) {
            dd("❌ Falha na conexão: ($errno) $errstr<br>");
        } else {
            dd("✅ Conexão bem-sucedida com $host na porta $port<br>");
            
        }

        $email = $data['feedback_user_email'];
        $nome = $data['feedback_user_name'];
        $job_id = $data['job_id'];
        
        $mail = new PHPMailer(true);
        $hash = sha1($data['feedback_user_email'].time());

        $job = Job::where('id', $job_id)->first();
        
        $job->update([
            'feedback_user_name' => $data['feedback_user_name'],
            'feedback_user_email' => $data['feedback_user_email'],
            'feedback_user_phone' => $data['feedback_user_phone'],
            'feedback_hash' => $hash
        ]);

        if (!$email) {
            return response()->json(['error' => 'false', 'message' => 'Sem E-mail do destinatário.']);
        }

        try {
            // Configurações do servidor SMTP do Gmail
            $mail->isSMTP();

            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            /*$mail->Username = 'think.ideias.1@gmail.com'; // Seu endereço de e-mail
            $mail->Password = 'dhqg bibw laok mawt';  // Senha de app gerada no Google*/

            $mail->Username = 'gui9788534514088@gmail.com'; // Seu endereço de e-mail
            $mail->Password = 'amky uxiz mkxx huif';  // Senha de app gerada no Google
            
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            // Remetente e destinatário
            $mail->setFrom('no-reply@think.com', 'Think');
            $mail->addAddress($email, $nome); // Adicione o destinatário

            // Conteúdo do e-mail
            $mail->isHTML(true);
            $mail->Subject = 'Obrigado pela Parceria!';

            $mail->Body = '<!DOCTYPE html>
                <html lang="pt-BR">
                    <head>
                        <meta charset="UTF-8" />
                        <title>Agradecimento e Solicitação</title>
                    </head>
                    <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
                        <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; background-color: #ffffff;">
                        <!-- Cabeçalho -->
                        <tr>
                            <td align="center" style="padding: 20px 0; background-color: #0056b3; color: #ffffff;">
                            <h1 style="margin: 0; font-size: 24px; font-weight: bold;">Obrigado pela Parceria!</h1>
                            </td>
                        </tr>
                        <!-- Corpo -->
                        <tr>
                            <td style="padding: 30px; color: #333333; text-align: center; font-size: 16px;">
                            <p style="margin: 0;">
                                Olá! 😊<br /><br />
                                Gostaríamos de expressar nossa gratidão pela confiança e parceria.
                                Antes de prosseguir, queremos dizer que sua opinião é muito importante para nós.
                                Solicitamos gentilmente que clique no botão abaixo para responder a algumas perguntas rápidas. 
                            </p>
                            <br />
                            <!-- Botão -->
                            <table align="center" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                <td align="center" style="background-color: #286ea7; border-radius: 4px;">
                                    <a href="http://localhost:4200/external/feedback/' . $job_id . '/' . $hash . '" target="_blank" style="display: block; padding: 12px 20px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: bold; font-family: Arial, sans-serif;">
                                        Visualizar
                                    </a>
                                </td>
                                </tr>
                            </table>
                            </td>
                        </tr>
                        <!-- Rodapé -->
                        <tr>
                            <td align="center" style="padding: 20px; font-size: 12px; color: #777777; background-color: #f5f5f5;">
                            Caso tenha alguma dúvida, não hesite em nos contatar.<br />
                            <strong>Think</strong>
                            </td>
                        </tr>
                        </table>
                    </body>
                </html>';
            
            // Enviar o e-mail
            $mail->send();
        } catch (Exception $e) {
            return response()->json(['error' => 'true', 'message' => "Erro ao enviar mensagem: {$mail->ErrorInfo}"]);
        }

        return response()->json(['error' => 'false', 'message' => 'Email de confirmação enviado ao cliente.']);
    }

    public function stand()
    {
        return $this->hasOne('App\Stand', 'job_id');
    }

    public function job_activity()
    {
        return $this->belongsTo('App\JobActivity', 'job_activity_id');
    }

    public function client()
    {
        return $this->belongsTo('App\Client', 'client_id');
    }

    public function job_type()
    {
        return $this->belongsTo('App\JobType', 'job_type_id');
    }

    public function main_expectation()
    {
        return $this->belongsTo('App\JobMainExpectation', 'main_expectation_id');
    }

    public function level()
    {
        return $this->belongsTo('App\JobLevel', 'level_id');
    }

    public function how_come()
    {
        return $this->belongsTo('App\JobHowCome', 'how_come_id');
    }

    public function agency()
    {
        return $this->belongsTo('App\Client', 'agency_id');
    }

    public function attendance()
    {
        return $this->belongsTo('App\Employee', 'attendance_id')->withTrashed();
    }

    public function attendance_comission()
    {
        return $this->belongsTo('App\Employee', 'attendance_comission_id');
    }

    public function competition()
    {
        return $this->belongsTo('App\JobCompetition', 'competition_id');
    }

    public function status()
    {
        return $this->belongsTo('App\JobStatus', 'status_id');
    }

    public function briefing()
    {
        return $this->hasOne('App\Briefing', 'job_id');
    }

    public function budget()
    {
        return $this->hasOne('App\Budget', 'job_id');
    }

    public function levels()
    {
        return $this->belongsToMany('App\JobLevel', 'job_level_job', 'job_id', 'level_id');
    }

    public function files()
    {
        return $this->hasMany('App\JobFile', 'job_id');
    }

    public function setNotClientAttribute($value)
    {
        $this->attributes['not_client'] = ucwords(mb_strtolower($value));
    }

    public function setEventAttribute($value)
    {
        $this->attributes['event'] = ucwords(mb_strtolower($value));
    }

    public function setLastProviderAttribute($value)
    {
        $this->attributes['last_provider'] = ucwords(mb_strtolower($value));
    }

    public function setBudget_valueAttribute($value)
    {
        $this->attributes['budget_value'] = (float) str_replace(',', '.', str_replace('.', '', $value));
    }

    public function setAreaAttribute($value)
    {
        $this->attributes['area'] = (float) str_replace(',', '.', str_replace('.', '', $value));
    }

    public function setDeadlineAttribute($value)
    {
        $this->attributes['deadline'] = substr($value, 0, 10);
    }

    public function checkin()
    {
        return $this->belongsTo('App\Checkin', 'id', 'job_id');
    }
}
