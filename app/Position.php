<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $table = 'position';

    protected $fillable = [
        'name', 'description'
    ];

    public static function list() {
        $positions = Position::orderBy('name', 'asc')->paginate(100);
        
        return [ 'pagination' => $positions ];
    }

    public static function filter(array $data) {
        $name = isset($data['name']) ? $data['name'] : '';
        $positions = Position::where('name', 'LIKE', '%' . $name . '%')
            ->orderBy('name', 'asc')->paginate(50);

        return [ 'pagination' => $positions ];
    }
}
