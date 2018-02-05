<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employee';

    protected $fillable = [
        'name', 'payment', 'position_id', 'department_id'
    ];

    protected $hidden = [
        'payment'
    ];

    public static function list() {
        $employees = Employee::select()
        ->orderBy('name', 'asc')
        ->get();

        foreach($employees as $employee) {
            $employee->department;
        }

        return $employees;
    }

    public static function canInsertClients() {
        $insertClients = Functionality::where('description', '=', 'Cadastrar um cliente')->first();
        $insertMyClients = Functionality::where('description', '=', 'Cadastrar um cliente (atendimento)')->first();

        return Employee::select('employee.id', 'employee.name', 'employee.position_id', 'employee.department_id')
        ->join('user', 'user.employee_id', '=', 'employee.id')
        ->join('user_functionality', 'user_functionality.user_id', '=', 'user.id')
        ->where('user_functionality.functionality_id', '=', $insertClients->id)
        ->orWhere('user_functionality.functionality_id', '=', $insertMyClients->id)
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
        return $this->belongsTo('App\Position', 'position_id');
    }

    public function department() {
        return $this->belongsTo('App\Department', 'department_id');
    }
}
