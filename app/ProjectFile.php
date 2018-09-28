<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use ZipArchive;

class ProjectFile extends Model {
    
    protected $table = 'project_file';
    protected $fillable = [
        'task_id', 'responsible_id', 'name', 'original_name', 'type'
    ];

    public function moveFile() {
        $browserFiles = [];
        $path = resource_path('assets/files/project-files');

        if(!is_dir($path)) {
            mkdir($path);
        }

        if(is_file(sys_get_temp_dir() . '/' .  $this->original_name)) {
            rename(sys_get_temp_dir() . '/' .  $this->original_name, $path . '/' . $this->name);
        }
    }

    public static function downloadFile($id) {
        $projectFile = ProjectFile::find($id);

        if(is_null($projectFile)) {
            throw new \Exception('O arquivo solicitado não existe.');
        }

        $path = resource_path('assets/files/project-files/') . $projectFile->name;

        FileHelper::checkIfExists($path);
        return $path;
    }

    public static function downloadAllFiles($taskId) {
        $projectFiles = ProjectFile::where('task_id', '=', $taskId)->get();
        $zip = new ZipArchive;
        $path = sys_get_temp_dir() . '/' . $taskId . '.zip';

        if($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === false) {
            throw new \Exception('Erro ao criar o arquivo zip.');            
        }

        //$paths = [];
        foreach($projectFiles as $projectFile) {
            $name = $projectFile->name;
            $original_name = $projectFile->name . '.' . $projectFile->type;
            $pathFile = resource_path('assets/files/project-files/') . $name;
            $zip->addFile($pathFile, $original_name);
            //$paths[] = $pathFile;
        }

        $zip->close();
        //dd($paths);
        return $path;
    }

    public static function insertAll(array $data) {
        $project_files = [];
        
        foreach($data as $projectFile) {
            $project_files[] = ProjectFile::insert($projectFile);
        }

        if(count($project_files) ==  0) return [];

        $projectFile = $project_files[0];
        $task1 = $projectFile->task;

        $message1 = $projectFile->responsible->name . ': Entrega de ' . $task1->getTaskName() . ' da ';
        $message1 .= $task1->job->getJobName();
        $message1 .= ' para ' . $task1->job->attendance->name;

        $notificationCount = Notification::where('message', '=', $message1)
            ->where('info', '=', $task1->id)
            ->get()
            ->count();

        if($notificationCount == 0) {
            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message1
            ], NotificationSpecial::createMulti([
                'user_id' => $task1->job->attendance->user->id,
                'message' => $message1
            ]), 'Entrega de projeto', $task1->id);
        }

        return $project_files;
    }

    public static function insert(array $data) {
        $original_name = isset($data['original_name']) ? $data['original_name'] : null;
        $task_id = isset($data['task']['id']) ? $data['task']['id'] : null;
        $responsible_id = User::logged()->employee->id;
        $tempPath = sys_get_temp_dir() . '/' .  $original_name;
        $name = sha1($tempPath . time());
        $type = (new \SplFileInfo($tempPath))->getExtension();

        $project_file = new ProjectFile(array_merge($data, [
            'task_id' => $task_id,
            'name' => $name,
            'type' => $type,
            'responsible_id' => $responsible_id
        ]));

        $project_file->save();
        $project_file->moveFile();
        $project_file->task->insertMemorial();
        
        return $project_file;
    }

    /*
    public static function edit(array $data) {
        $id = isset($data['id']) ? $data['id'] : null;
        $task_id = isset($data['task']['id']) ? $data['task']['id'] : null;
        $responsible_id = User::logged()->employee->id;

        $project_file = ProjectFile::find($id);
        
        $project_file->update(array_merge($data, [
            'task_id' => $task_id,
            'responsible_id' => $responsible_id
        ]));
        
        return $project_file;
    }
    */

    public static function remove($id) {
        $project_file = ProjectFile::find($id);
        $project_file->delete();
        $project_file->deleteFile();
    }


    public function deleteFile() {
        $browserFiles = [];
        $path = resource_path('assets/files/project-files');
        $file = $path . '/' . $this->name;

        if(is_file($file)) {
            unlink($file);
        }
    }

    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id');
    }

    public function responsible()
    {
        return $this->belongsTo('App\Employee', 'responsible_id');
    }
}
