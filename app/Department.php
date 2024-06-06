<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'department';

    protected $fillable = [
       'description'
    ];

    public static function list() {
        $departments = Department::orderBy('description', 'asc')->paginate(100);
        
        return [ 'pagination' => $departments ];
    }

    public static function filter(array $data) {
        $description = isset($data['description']) ? $data['description'] : '';
        $departments = Department::where('description', 'LIKE', '%' . $description . '%')
            ->orderBy('description', 'asc')->paginate(50);

        return [ 'pagination' => $departments ];
    }
}
