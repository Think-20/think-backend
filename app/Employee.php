<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employee';

    protected $fillable = [
        'name', 'payment', 'positionId', 'departmentId'
    ];

    protected $hidden = [
        'payment'
    ];

    public static function list() {
        return Employee::select()
        ->orderBy('name', 'asc')
        ->get();
    }

    public static function canInsertClients() {
        $insertClients = Functionality::where('description', '=', 'Cadastrar um cliente')->first();
        $insertMyClients = Functionality::where('description', '=', 'Cadastrar um cliente (atendimento)')->first();

        return Employee::select('employee.id', 'employee.name', 'employee.positionId', 'employee.departmentId')
        ->join('user', 'user.employeeId', '=', 'employee.id')
        ->join('user_functionality', 'user_functionality.userId', '=', 'user.id')
        ->where('user_functionality.functionalityId', '=', $insertClients->id)
        ->orWhere('user_functionality.functionalityId', '=', $insertMyClients->id)
        ->orderBy('name', 'asc')
        ->distinct()
        ->get();
    }

    public static function filter($query) {
        return Employee::where('name', 'like', $query . '%')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function position() {
        return $this->belongsTo('App\Position', 'positionId');
    }

    public function department() {
        return $this->belongsTo('App\Department', 'departmentId');
    }
}
