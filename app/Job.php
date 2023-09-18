<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DB;
use DateTime;
use DateInterval;
use Exception;
use Illuminate\Support\Carbon;

class Job extends Model
{
    public $timestamps = true;

    protected $table = 'job';

    protected $fillable = [
        'code',
        'job_activity_id', 'client_id', 'event', 'deadline', 'job_type_id', 'agency_id', 'attendance_id',
        'rate', 'competition_id', 'last_provider', 'not_client', 'how_come_id', 'approval_expectation_rate',
        'main_expectation_id', 'budget_value', 'status_id', 'note', 'place', 'area', 'moments', 'created_at', 'time_to_aproval'
    ];

    protected $dates = [
        'created_at', 'updated_at'
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
        if ($this->status->id == '3') {
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

            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message
            ], NotificationSpecial::createMulti([
                'user_id' => $task->responsible->user->id,
                'message' => $message,
            ], [
                'user_id' => $this->attendance->user->id,
                'message' => $message
            ]), 'Alteração de job', $task->id);
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
            ])
        );

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
        $jobs = Job::orderBy('available_date', 'asc')->paginate(20);

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
        $job->responsibles();
        $job->history();
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
            mkdir($path);
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

    public function tasks()
    {
        return $this->hasMany('App\Task', 'job_id')->with(
            'project_files',
            'project_files.responsible',
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
}
