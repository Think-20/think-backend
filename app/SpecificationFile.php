<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use ZipArchive;

class SpecificationFile extends Model {
    
    protected $table = 'specification_file';
    protected $fillable = [
        'task_id', 'responsible_id', 'name', 'original_name', 'type'
    ];

    public function moveFile() {
        $path = env('FILES_FOLDER') . '/specification-files';

        if(!is_dir($path)) {
            mkdir($path);
        }

        if(is_file(sys_get_temp_dir() . '/' .  $this->original_name)) {
            rename(sys_get_temp_dir() . '/' .  $this->original_name, $path . '/' . $this->name);
        }
    }

    public static function downloadFile($id) {
        $specificationFile = SpecificationFile::find($id);

        if(is_null($specificationFile)) {
            throw new \Exception('O arquivo solicitado não existe.');
        }

        $path = env('FILES_FOLDER') . '/specification-files/' . $specificationFile->name;

        FileHelper::checkIfExists($path);
        return $path;
    }

    public static function downloadAllFiles($taskId) {
        $specificationFiles = SpecificationFile::where('task_id', '=', $taskId)->get();
        $zip = new ZipArchive;
        $path = sys_get_temp_dir() . '/' . $taskId . '.zip';

        if($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === false) {
            throw new \Exception('Erro ao criar o arquivo zip.');            
        }

        //$paths = [];
        foreach($specificationFiles as $specificationFile) {
            $name = $specificationFile->name;
            $original_name = $specificationFile->name . '.' . $specificationFile->type;
            $pathFile = env('FILES_FOLDER') . '/specification-files/' . $name;
            $zip->addFile($pathFile, $original_name);
            //$paths[] = $pathFile;
        }

        $zip->close();
        //dd($paths);
        return $path;
    }

    public static function insertAll(array $data) {
        $specification_files = [];
        
        foreach($data as $specificationFile) {
            $specification_files[] = SpecificationFile::insert($specificationFile);
        }

        if(count($specification_files) ==  0) return [];

        $specificationFile = $specification_files[0];
        $task1 = $specificationFile->task;

        $message1 = 'Entrega de memorial descritivo da ';
        $message1 .= $task1->job->getJobName();

        if( !Notification::hasPrevious($message1, 'Entrega de memorial', $task1->id) ) {
            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message1
            ], NotificationSpecial::createMulti([
                'user_id' => $task1->job->attendance->user->id,
                'message' => $message1
            ]), 'Entrega de memorial', $task1->id);
        }

        return $specification_files;
    }

    public static function insert(array $data) {
        $original_name = isset($data['original_name']) ? $data['original_name'] : null;
        $task_id = isset($data['task']['id']) ? $data['task']['id'] : null;
        $responsible_id = User::logged()->employee->id;
        $tempPath = sys_get_temp_dir() . '/' .  $original_name;
        $name = sha1($tempPath . time());
        $type = (new \SplFileInfo($tempPath))->getExtension();

        $specification_file = new SpecificationFile(array_merge($data, [
            'task_id' => $task_id,
            'name' => $name,
            'type' => $type,
            'responsible_id' => $responsible_id
        ]));

        $specification_file->save();
        $specification_file->moveFile();

        $task = Task::find($task_id);
        if($task->task->job_activity->description == 'Projeto externo') {
            $specification_file->task->insertBudget();
        } else if($task->task->job_activity->description == 'Projeto') {
            $specification_file->task->insertBudget();
        } else if($task->task->job_activity->description == 'Modificação') {
            $specification_file->task->insertBudgetModify();
        }        
        $specification_file->updateDone($task);
        
        return $specification_file;
    }

    public static function remove($id) {
        $specificationFile = SpecificationFile::find($id);
        $task = $specificationFile->task;
        $specificationFile->deleteFile();
        $specificationFile->delete();
        $specificationFile->updateDone($task);
    }

    public function updateDone(Task $task) {
        if($task->specification_files->count() > 0) {
            $task->done = 1;
        } else {
            $task->done = 0;
        }

        $task->save();
    }

    public function deleteFile() {
        $path = env('FILES_FOLDER') . '/specification-files';
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
        return $this->belongsTo('App\Employee', 'responsible_id')->withTrashed();
    }
}
