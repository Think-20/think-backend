<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DB;
use DateTime;
use DateInterval;

class Job extends Model
{
    public $timestamps = true;

    protected $table = 'job';

    protected $fillable = [
        'code', 
        'job_activity_id', 'client_id', 'event', 'deadline', 'job_type_id', 'agency_id', 'attendance_id',
        'rate', 'competition_id', 'last_provider', 'not_client', 'how_come_id', 'approval_expectation_rate', 
        'main_expectation_id', 'budget_value', 'status_id', 'note'
    ];

    protected $dates = [
        'created_at', 'updated_at'
    ];

    public static function loadForm() {
        return [
            'job_activities' => JobActivity::all(),
            'job_types' => JobType::all(),
            'attendances' => Employee::canInsertClients(),
            'competitions' => JobCompetition::all(),
            'main_expectations' => JobMainExpectation::all(),
            'levels' => JobLevel::all(),
            'how_comes' => JobHowCome::all(),
            'status' => JobStatus::all(),
        ];
    }

    public static function edit(array $data) {
        Job::checkData($data, true);

        $id = $data['id'];
        $agency_id = isset($data['agency']['id']) ? $data['agency']['id'] : null;
        $client_id = isset($data['client']['id']) ? $data['client']['id'] : null;
        $job = Job::find($id);
        $oldJob = clone $job;
        $job->update(
            array_merge($data, [
                'job_activity_id' => $data['job_activity']['id'],
                'client_id' => $client_id,
                'agency_id' => $agency_id,
                'main_expectation_id' => $data['main_expectation']['id'],
                'status_id' => $data['status']['id'],
                'how_come_id' => $data['how_come']['id'],
                'attendance_id' => $data['attendance']['id'],
                'competition_id' => $data['competition']['id'],
            ])
        );

        $arrayLevels = !isset($data['levels']) ? [] : $data['levels'];
        $job->saveLevels($arrayLevels);

        $arrayFiles = !isset($data['files']) ? [] : $data['files'];
        $job->editFiles($arrayFiles);

        return $job;
    }

    public static function insert(array $data) {
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

    public static function downloadFile($id, $type, $file) {
        $job = Job::find($id);
        $user = User::logged();

        if(is_null($job)) {
            throw new \Exception('O job solicitado não existe.');
        }

        switch($type) {
            case 'job': {
                $path = resource_path('assets/files/jobs/') . $job->id . '/' . $file;
                break;
            }
            case 'stand': {
                $path = resource_path('assets/files/stands/') . $job->briefing->stand->id . '/' . $job->briefing->stand->{$file};
                break;
            } 
            default: {
                throw new \Exception('O tipo de arquivo solicitado não existe. ' . $type);
            }
        }

        FileHelper::checkIfExists($path);
        return $path;
    }

    public static function remove($id) {
        $job = Job::find($id);
        $oldJob = clone $job;
        $job->levels()->detach();
        $job->tasks->delete();
        $job->deleteFiles();
        $job->briefing ? $job->briefing->delete() : null;
        $job->budget ? $job->budget->delete() : null;
        $job->delete();
    }

    public static function list() {
        $jobs = Job::orderBy('available_date', 'asc')->paginate(20);

        foreach($jobs as $job) {
            $job->agency;
            #$job->creation;
            $job->job_activity;
            $job->job_type;
            $job->attendance;
            $job->client;
            $job->status;
        }

        return $jobs;
    }

    public static function get(int $id) {
        $job = Job::find($id);
        $job->job_activity;
        $job->job_type;
        $job->client;
        $job->main_expectation;
        $job->levels;
        $job->how_come;
        $job->agency;
        $job->attendance;
        #$job->creation;
        $job->competition;
        $job->files;
        $job->status;
        #$job->creation;
        $job->briefing ? $job->briefing->get() : null;
        $job->budget ? $job->budget->get() : null;

        return $job;
    }

    public static function filter($params) {
        $iniDate = isset($params['iniDate']) ? $params['iniDate'] : null;
        $finDate = isset($params['finDate']) ? $params['finDate'] : null;
        $orderBy = isset($params['orderBy']) ? $params['orderBy'] : 'created_at';
        $status = isset($params['status']) ? $params['status'] : null;
        $paginate = isset($params['paginate']) ? $params['paginate'] : true;

        $jobs = Job::selectRaw('*, job.id as id')
        ->with('job_activity', 'job_type', 'client', 'main_expectation', 'levels',
        'how_come', 'agency', 'attendance', 'competition', 'files', 'status');

        if( ! is_null($status) ) {
            $jobs->where('status_id', '=', $status);
        }

        if($orderBy == 'created_at') {
            $jobs->orderBy('created_at', 'DESC');
        }

        if($paginate) {
            $paginate = $jobs->paginate(50);
            foreach($paginate as $job) {
                $job->creation();
            }
            $result = $paginate->items();
            $page = $paginate->currentPage();
            $total = $paginate->total();
        } else {
            $result = $jobs->get();
            foreach($result as $job) {
                $job->creation();
            }
            $total = $jobs->count();
            $page = 0;
        }

        return [
            'data' => $result,
            'total' => $total,
            'page' => $page
        ];
    }

    
    #My Job#
    public static function editMyJob(array $data) {
        Job::checkData($data, true);

        $id = $data['id'];
        $job = Job::find($id);
        $agency_id = isset($data['agency']['id']) ? $data['agency']['id'] : null;
        $client_id = isset($data['client']['id']) ? $data['client']['id'] : null;

        if($job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para editar esse job.');
        }

        $oldJob = clone $job;
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

        $arrayLevels = !isset($data['levels']) ? [] : $data['levels'];
        $job->saveLevels($arrayLevels);

        $arrayFiles = !isset($data['files']) ? [] : $data['files'];
        $job->editFiles($arrayFiles);

        return $job;
    }

    public function saveLevels(array $data) {
        $this->levels()->detach();
        
        foreach($data as $level) {
            $this->levels()->attach($level['id']);
        }
    }

    public static function downloadFileMyJob($id, $type, $file) {
        $job = Job::find($id);
        $user = User::logged();
        
        if($job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para fazer downloads desse job.');
        }

        if(is_null($job)) {
            throw new \Exception('O job solicitado não existe.');
        }

        switch($type) {
            case 'job': {
                $path = resource_path('assets/files/jobs/') . $job->id . '/' . $file;
            }
            case 'stand': {
                $path = resource_path('assets/files/stands/') . $job->stand->id . '/' . $job->stand->{$file};
            } 
            default: {
                throw new \Exception('O tipo de arquivo solicitado não existe. ' . $type);
            }
        }

        FileHelper::checkIfExists($path);

        return $path;
    }
    
    public static function removeMyJob($id) {
        $job = Job::find($id);

        if($job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para remover esse job.');
        }

        $oldJob = clone $job;
        $job->presentations()->detach();
        $job->levels()->detach();
        $job->deleteFiles();
        $job->delete();
    }

    public static function listMyJob() {
        $jobs = Job::orderBy('available_date', 'asc')
         ->where('attendance_id', '=', User::logged()->employee->id)
         ->paginate(20);

        foreach($jobs as $job) {
            $job->agency;
            $job->creation;
            $job->job_activity;
            $job->job_type;
            $job->attendance;
            $job->client;
            $job->status;
        }

        return $jobs;
    }

    public static function getMyJob(int $id) {
        $job = Job::find($id);

        if($job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para visualizar esse job.');
        }

        $job->job_activity;
        $job->job_type;
        $job->client;
        $job->main_expectation;
        $job->levels;
        $job->how_come;
        $job->agency;
        $job->attendance;
        $job->status;
        $job->creation;
        $job->competition;
        $job->files;

        //Job::getJobChild($job);
        return $job;
    }

    public static function filterMyJob($params) {
        $iniDate = isset($params['iniDate']) ? $params['iniDate'] : null;
        $finDate = isset($params['finDate']) ? $params['finDate'] : null;
        $orderBy = isset($params['orderBy']) ? $params['orderBy'] : 'available_date';
        $status = isset($params['status']) ? $params['status'] : null;
        $paginate = isset($params['paginate']) ? $params['paginate'] : true;
        $user = User::logged();
        $jobs = Job::where('attendance_id', '=', $user->employee->id);

        if($orderBy == 'available_date') {
            $jobs->orderBy('available_date', 'ASC');
        } else if($orderBy == 'created_at') {
            $jobs->orderBy('created_at', 'DESC');
        }
        $jobs->orderBy('attendance_id', 'ASC');


        if($iniDate != null && $finDate != null) {
            $jobs->where('available_date', '>=', $iniDate);
            $jobs->where('available_date', '<=', $finDate);
        }
        
        if($status != null) {
            $jobs->where('status_id', '=', $status);
        }

        if($paginate) {
            $jobs = $jobs->paginate(50);
            
            foreach($jobs as $job) {
                $job->job_activity;
                $job->job_type;
                $job->client;
                $job->main_expectation;
                $job->levels;
                $job->how_come;
                $job->agency;
                $job->attendance;
                $job->creation;
                $job->competition;
                $job->files;
                $job->status;
            }
        } else {
            $jobs = $jobs->get();
            
            foreach($jobs as $job) {
                $job->job_activity;
                $job->job_type;
                $job->client;
                $job->main_expectation;
                $job->levels;
                $job->how_come;
                $job->agency;
                $job->attendance;
                $job->creation;
                $job->competition;
                $job->files;
                $job->status;
            }

            $jobs = ['data' => $jobs, 'page' => 0, 'total' => $jobs->count()];
        }

        return $jobs;
    }

    public static function generateCode() {
        $result = DB::table('job')
        ->select(DB::raw('(MAX(code) + 1) as code'))
        ->where(DB::raw('YEAR(CURRENT_DATE())'), '=', DB::raw('YEAR(created_at)'))
        ->first();

        if($result->code == null) {
            return 1;
        }

        return $result->code; 
    }

    public function saveFiles(array $data) {
        $path = resource_path('assets/files/jobs/') . $this->id;

        if(!is_dir($path)) {
            mkdir($path);
        }

        foreach($data as $file) {
            rename(sys_get_temp_dir() . '/' .  $file['name'], $path . '/' . $file['name']);
            $this->files()->save(new JobFile([
                'job_id' => $this->id,
                'filename' => $file['name']
            ]));
        }
    }

    public function editFiles(array $data) {
        $browserFiles = [];
        $path = resource_path('assets/files/jobs/') . $this->id;

        if(!is_dir($path)) {
            mkdir($path);
        }

        foreach($data as $file) {
            $browserFiles[] = $file['name'];
            $oldFile = $this->files()
            ->where('job_file.filename', '=', $file['name'])
            ->first();

            if(is_file(sys_get_temp_dir() . '/' .  $file['name'])) {
                // Substituir / criar arquivo em caso de não existir
                rename(sys_get_temp_dir() . '/' .  $file['name'], $path . '/' . $file['name']);
                
                if(is_null($oldFile)) {
                    $this->files()->save(new JobFile([
                        'job_id' => $this->id,
                        'filename' => $file['name']
                    ]));
                }
            }
        }

        foreach($this->files as $file) {
            try {
                if(!in_array($file->filename, $browserFiles)) {
                    unlink($path . '/' . $file->filename);
                    $file->delete();
                }
            } catch(\Exception $e) {}
        }
    }

    public function deleteFiles() {
        $path = resource_path('assets/files/jobs/') . $this->id;
        foreach($this->files as $file) {
            try {
                unlink($path . '/' . $file->filename);
                $file->delete();
            } catch(\Exception $e) {}
        } 
    }

    public static function checkData(array $data, $editMode = false) {
        if(!isset($data['job_activity']['id'])) {
            throw new \Exception('Atividade do job não informado!');
        }

        if(!isset($data['status']['id'])) {
            throw new \Exception('Status não informado!');
        }

        if(!isset($data['main_expectation']['id'])) {
            throw new \Exception('Expectativa principal do job não informada!');
        }

        if(!isset($data['how_come']['id'])) {
            throw new \Exception('Motivo do job não informado!');
        }

        if(!isset($data['job_type']['id']) && !$editMode) {
            throw new \Exception('Tipo de job do job não informado!');
        }

        if(!isset($data['attendance']['id'])) {
            throw new \Exception('Atendimento do job não informado!');
        }

        if(!isset($data['competition']['id'])) {
            throw new \Exception('Concorrência do job não informada!');
        }
    }

    public function tasks() {
        return $this->hasMany('App\Task', 'job_id');
    }

    public function creation() {
        foreach($this->tasks as $task) {
            if($task->job_activity->description == 'Projeto') {
                $this->creation = $task->responsible;
            }
        }
    }

    public function stand() {
        return $this->hasOne('App\Stand', 'job_id');
    }

    public function job_activity() {
        return $this->belongsTo('App\JobActivity', 'job_activity_id');
    }

    public function client() {
        return $this->belongsTo('App\Client', 'client_id');
    }

    public function job_type() {
        return $this->belongsTo('App\JobType', 'job_type_id');
    }

    public function main_expectation() {
        return $this->belongsTo('App\JobMainExpectation', 'main_expectation_id');
    }

    public function level() {
        return $this->belongsTo('App\JobLevel', 'level_id');
    }

    public function how_come() {
        return $this->belongsTo('App\JobHowCome', 'how_come_id');
    }

    public function agency() {
        return $this->belongsTo('App\Client', 'agency_id');
    }

    public function attendance() {
        return $this->belongsTo('App\Employee', 'attendance_id');
    }

    public function competition() {
        return $this->belongsTo('App\JobCompetition', 'competition_id');
    }

    public function status() {
        return $this->belongsTo('App\JobStatus', 'status_id');
    }

    public function briefing() {
        return $this->hasOne('App\Briefing', 'job_id');
    }

    public function budget() {
        return $this->hasOne('App\Budget', 'job_id');
    }

    public function levels() {
        return $this->belongsToMany('App\JobLevel', 'job_level_job', 'job_id', 'level_id');
    }

    public function files() {
        return $this->hasMany('App\JobFile', 'job_id');
    }

    public function setBudget_valueAttribute($value) {
        $this->attributes['budget_value'] = (float) str_replace(',', '.', str_replace('.', '', $value));
    }
}
