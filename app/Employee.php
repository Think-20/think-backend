<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DateTime;
use DB;

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
        $employees = Employee::with('user', 'position', 'department')
        ->orderBy('name', 'asc')
        ->paginate(20);

        return [
            'pagination' => $employees,
            'updatedInfo' => Employee::updatedInfo()
        ];
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

    public static function filter(array $data) {
        $search = isset($data['search']) ? $data['search'] : null;
        $departmentId = isset($data['department']['id']) ? $data['department']['id'] : null;
        $positionId = isset($data['position']['id']) ? $data['position']['id'] : null;
        $query = Employee::with('user', 'position', 'department');

        if( ! is_null($search) ) {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        if( ! is_null($departmentId) ) {
            $query->where('department_id', '=', $departmentId);
        }

        if( ! is_null($positionId) ) {
            $query->where('position_id', '=', $positionId);
        }

        $query->orderBy('name', 'asc');
        $employees = $query->paginate(20);

        return [
            'pagination' => $employees,
            'updatedInfo' => Employee::updatedInfo()
        ];
    }

    

    public static function edit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $employee = Employee::find($id);
            $employee->makeVisible('payment');
            $employee->department_id = isset($data['department']['id']) ? $data['department']['id'] : null;
            $employee->position_id = isset($data['position']['id']) ? $data['position']['id'] : null;
            $employee->update($data);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();
        
        try {
            $employee = new Employee($data);
            $employee->department_id = isset($data['department']['id']) ? $data['department']['id'] : null;
            $employee->position_id = isset($data['position']['id']) ? $data['position']['id'] : null;
            $employee->save();
            DB::commit();
            return $employee;
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function remove($id) {
        DB::beginTransaction();
        
        try {
            $employee = Employee::find($id);
            $employee->delete();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function get(int $id) {
        $employee = Employee::with('user', 'position', 'department', 'updatedBy')
        ->where('employee.id', '=', $id)
        ->first();
                
        if(is_null($employee)) {
            return null;
        }

        $employee->makeVisible('payment');
        return $employee;
    }

    public static function updatedInfo() {
        $lastData = Employee::orderBy('updated_at', 'desc')->limit(1)->first();

        if($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->updated_at))->format('d/m/Y'),
            'employee' => $lastData->updatedBy->name
        ];
    }

    public function position() {
        return $this->belongsTo('App\Position', 'position_id');
    }

    public function user() {
        return $this->hasOne('App\User', 'employee_id');
    }

    public function updatedBy() {
        return $this->belongsTo('App\Employee', 'updated_by');
    }

    public function department() {
        return $this->belongsTo('App\Department', 'department_id');
    }
    
    public function notifications() {
        return $this->morphMany(Notification::class, 'notifier');
    }
}
