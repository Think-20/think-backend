<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Exception;
use ZipArchive;

class ProjectFile extends Model
{

    protected $table = 'project_file';
    protected $fillable = [
        'task_id', 'responsible_id', 'name', 'original_name', 'type'
    ];

    public function moveFile()
    {
        $browserFiles = [];
        $path = env('FILES_FOLDER') . '/project-files';

        if (!is_dir($path)) {
            try {
                mkdir($path);
            } catch (Exception $e) {
                $sudoCommand = "sudo mkdir -p $path";
                shell_exec($sudoCommand);
            }
        }

        if (is_file(sys_get_temp_dir() . '/' .  $this->original_name)) {
            $res = rename(sys_get_temp_dir() . '/' .  $this->original_name, $path . '/' . $this->name);

            if (!$res) {
                throw new Exception('Erro ao mover o arquivo para a pasta de projetos');
            }
        } else {
            throw new Exception('Arquivo não encontrado para mover');
        }
    }

    public static function downloadFile($id)
    {
        $projectFile = ProjectFile::find($id);

        if (is_null($projectFile)) {
            throw new \Exception('O arquivo solicitado não existe.');
        }

        $path = env('FILES_FOLDER') . '/project-files/' . $projectFile->name;

        FileHelper::checkIfExists($path);
        return $path;
    }

    public static function downloadAllFiles($taskId)
    {
        $projectFiles = ProjectFile::where('task_id', '=', $taskId)->get();
        $zip = new ZipArchive;
        $path = sys_get_temp_dir() . '/' . $taskId . '.zip';

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === false) {
            throw new \Exception('Erro ao criar o arquivo zip.');
        }

        //$paths = [];
        foreach ($projectFiles as $projectFile) {
            $name = $projectFile->name;
            $original_name = $projectFile->name . '.' . $projectFile->type;
            $pathFile = env('FILES_FOLDER') . '/project-files/' . $name;
            $zip->addFile($pathFile, $original_name);
            //$paths[] = $pathFile;
        }

        $zip->close();
        //dd($paths);
        return $path;
    }

    public static function insertAll(array $data)
    {
        $project_files = [];

        foreach ($data as $projectFile) {
            $project_files[] = ProjectFile::insert($projectFile);
        }

        if (count($project_files) ==  0) return [];

        $projectFile = $project_files[0];
        $task1 = $projectFile->task;

        if ($task1->job_activity->description != 'Projeto externo') {
            $message1 = $projectFile->responsible->name . ': Entrega de ' . $task1->getTaskName() . ' da ';
            $message1 .= $task1->job->getJobName();
            $message1 .= ' para ' . $task1->job->attendance->name;
        } else {
            $message1 = $task1->job->attendance->name . ': Entrega de ' . $task1->getTaskName() . ' da ';
            $message1 .= $task1->job->getJobName();
        }

        if (!Notification::hasPrevious($message1, 'Entrega de projeto', $task1->id)) {
            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message1
            ], NotificationSpecial::createMulti([
                'user_id' => $task1->job->attendance->user->id,
                'message' => $message1
            ]), 'Entrega de projeto', $task1->id);
        }

        return $project_files;
    }

    public function updateDone(Task $task)
    {
        // if($task->project_files->count() > 0) {
        $task->done = 1;
        // } else {
        //     $task->done = 0;
        // }

        $task->save();
    }

    public static function insert(array $data)
    {
        $original_name = isset($data['original_name']) ? $data['original_name'] : null;
        $task_id = isset($data['task']['id']) ? $data['task']['id'] : null;
        $responsible = User::logged()->employee;
        $tempPath = sys_get_temp_dir() . '/' .  $original_name;
        $name = sha1($tempPath . time());
        $type = (new \SplFileInfo($tempPath))->getExtension();

        $project_file = new ProjectFile(array_merge($data, [
            'task_id' => $task_id,
            'name' => $name,
            'type' => $type,
            'responsible_id' => $responsible->id
        ]));

        $project_file->save();
        $project_file->moveFile();

        $task = $project_file->task;
        $newJobActivity = JobActivity::where('description', '=', 'Memorial descritivo')->first();

        $count = Task::where('task_id', $task->id)
            ->where('job_activity_id', $newJobActivity->id)
            ->count();

        if ($count == 0) {
            $task->insertAutomatic($newJobActivity, $task->job->attendance, $task->job->attendance);
        }

        $project_file->updateDone($task);
        return $project_file;
    }

    public static function remove($id)
    {
        $projectFile = ProjectFile::find($id);
        $task = $projectFile->task;
        $projectFile->deleteFile();
        $projectFile->delete();
        $projectFile->updateDone($task);
    }


    public function deleteFile()
    {
        $browserFiles = [];
        $path = env('FILES_FOLDER') . '/project-files';
        $file = $path . '/' . $this->name;

        if (is_file($file)) {
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
