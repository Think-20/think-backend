<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Exception;
use ZipArchive;

class FinanceiroFile extends Model
{

    protected $table = 'financeiro_files';
    protected $fillable = [
        'task_id',
        'responsible_id',
        'name',
        'original_name',
        'type'
    ];

    public function moveFile()
    {
        $path = env('FILES_FOLDER') . '/financeiro-files';

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
            throw new Exception('Arquivo não encontrado para mover projectFile');
        }
    }

    public static function downloadFile($id)
    {

        $projectFile = FinanceiroFile::find($id);
        if (is_null($projectFile)) {
            throw new \Exception('O arquivo solicitado não existe.');
        }

        $zip = new ZipArchive;
        $path = sys_get_temp_dir() . '/' . $id . '.zip';

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === false) {
            throw new \Exception('Erro ao criar o arquivo zip.');
        }

        $name = $projectFile->name;
        $original_name = $projectFile->original_name;
        $pathFile = env('FILES_FOLDER') . '/financeiro-files/' . $name;
        $zip->addFile($pathFile, $original_name);

        $zip->close();
        return $path;
    }

    public static function downloadAllFiles($taskId)
    {
        $projectFiles = FinanceiroFile::where('task_id', '=', $taskId)->get();
        $zip = new ZipArchive;
        $path = sys_get_temp_dir() . '/' . $taskId . '.zip';

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === false) {
            throw new \Exception('Erro ao criar o arquivo zip.');
        }

        foreach ($projectFiles as $projectFile) {
            $name = $projectFile->name;
            $original_name =  $projectFile->original_name;
            $pathFile = env('FILES_FOLDER') . '/financeiro-files/' . $name;
            $zip->addFile($pathFile, $original_name);
        }

        $zip->close();
        return $path;
    }

    public static function insertAll(array $data)
    {
        $project_files = [];

        foreach ($data as $projectFile) {
            $project_files[] = FinanceiroFile::insert($projectFile);
        }

        if (count($project_files) ==  0) return [];

        return $project_files;
    }

    public static function insert(array $data)
    {
        $original_name = isset($data['original_name']) ? $data['original_name'] : null;
        $task_id = isset($data['task']['id']) ? $data['task']['id'] : null;
        $responsible = User::logged()->employee;
        $tempPath = sys_get_temp_dir() . '/' .  $original_name;
        $name = sha1($tempPath . time());
        $type = (new \SplFileInfo($tempPath))->getExtension();


        $project_file = new FinanceiroFile(array_merge($data, [
            'responsible_id' => $responsible->id,
            'task_id' => $task_id,
            'name' => $name,
            'type' => $type

        ]));

        $project_file->save();

        $project_file->moveFile();

        return $project_file;
    }

    public static function remove($id)
    {
        $projectFile = FinanceiroFile::find($id);
        $projectFile->deleteFile();
        $projectFile->delete();
    }


    public function deleteFile()
    {
        $path = env('FILES_FOLDER') . '/financeiro-files';
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
