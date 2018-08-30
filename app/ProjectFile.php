<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectFile extends Model {
    
    protected $table = 'project_file';
    protected $fillable = [
        'task_id', 'responsible_id', 'name', 'original_name', 'type'
    ];

    public static function insert(array $data) {
        $task_id = isset($data['task']['id']) ? $data['task']['id'] : null;
        $responsible_id = isset($data['task']['id']) ? $data['task']['id']: null;

        $project_file = new ProjectFile(array_merge($data, [
            'task_id' => $task_id,
            'responsible_id' => $responsible_id
        ]));

        $project_file->save();
        
        return $project_file;
    }

    public static function edit(array $data) {
        $id = isset($data['id']) ? $data['id'] : null;
        $task_id = isset($data['task']['id']) ? $data['task']['id'] : null;
        $responsible_id = isset($data['task']['id']) ? $data['task']['id']: null;

        $project_file = ProjectFile::find($id);
        
        $project_file->update(array_merge($data, [
            'task_id' => $task_id,
            'responsible_id' => $responsible_id
        ]));
        
        return $project_file;
    }

    public static function remove($id) {
        $project_file = ProjectFile::find($id);
        $project_file->delete();
    }

    public function responsible()
    {
        return $this->belongsTo('App\Employee', 'responsible_id');
    }
}
