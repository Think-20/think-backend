<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

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

    public static function insertAll(array $data) {
        $project_files = [];
        foreach($data as $projectFile) {
            $project_files[] = ProjectFile::insert($projectFile);
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

    public function responsible()
    {
        return $this->belongsTo('App\Employee', 'responsible_id');
    }
}
