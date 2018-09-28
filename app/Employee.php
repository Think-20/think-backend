<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model implements NotifierInterface
{
    protected $table = 'employee';

    protected $fillable = [
        'name', 'payment', 'position_id', 'department_id', 'schedule_active'
    ];

    protected $hidden = [
        'payment'
    ];

    public function getOficialId(): int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getLogo(): string {
        return '/assets/images/users/' . $this->user;
    }

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

    public function user() {
        return $this->hasOne('App\User', 'employee_id');
    }

    public function department() {
        return $this->belongsTo('App\Department', 'department_id');
    }
    
    public function notifications() {
        return $this->morphMany(Notification::class, 'notifier');
    }
}
