<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmployeeOfficeHours extends Model
{
    public $timestamps = false;

    protected $table = 'employee_office_hours';

    protected $fillable = [
        'entry', 'exit', 'employee_id'
    ];

    public static function edit(array $data) {
        $id = isset($data['id']) ? (int) $data['id'] : null;
        $employeeId = isset($data['employee']['id']) ? (int) $data['employee']['id'] : null;

        if($employeeId == null || ($employee = Employee::find($employeeId)) == null) {
            throw new \Exception('Informe corretamente o funcionário.');
        }
        

        if($id == null) {
            throw new \Exception('Informe corretamente o horário.');
        }

        $officeHour = EmployeeOfficeHours::find($id);
        $officeHour->update(array_merge($data, [
            'employee_id' => $employee->id
        ]));
    }

    public static function remove(int $id) {
        if($id == null) {
            throw new \Exception('Informe corretamente o horário.');
        }

        $officeHour = EmployeeOfficeHours::find($id);
        $officeHour->delete();
    }

    public static function get($id) {
        $officeHours = EmployeeOfficeHours::find($id);
        $officeHours->employee;
        return $officeHours;
    }

    public static function registerAnother(array $data) {
        $id = isset($data['id']) ? (int) $data['id'] : null;
        $employeeId = isset($data['employee']['id']) ? (int) $data['employee']['id'] : null;

        if($employeeId == null || ($employee = Employee::find($employeeId)) == null) {
            throw new \Exception('Informe corretamente o funcionário.');
        }
        
        $officeHours = null;

        if($id == null) {
            $officeHours = EmployeeOfficeHours::create(
                array_merge($data, [
                    'employee_id' => $employee->id
                ])
            );
            $officeHours->save();
        } else {
            $officeHours = EmployeeOfficeHours::find($id);
            if($officeHours == null) {
                throw new \Exception('Informe corretamente o horário que deseja alterar.');
            }
            $officeHours->update($data);
        }

        return $officeHours;
    }

    public static function registerYourself() {
        $employee = User::logged()->employee;
        $officeHours = EmployeeOfficeHours::where(['exit' => null])->first();

        if($officeHours == null) {
            $officeHours = EmployeeOfficeHours::create([
                'entry' => new \DateTime(),
                'employee_id' => $employee->id
            ]);
            $officeHours->save();
        } else {
            $officeHours->update([
                'exit' => new \DateTime()
            ]);
        }

        return $officeHours;
    }

    public static function showAnother($employeeId) {
        return EmployeeOfficeHours::where('employee_id', '=', $employeeId)->paginate(15);
    }

    public static function showYourself() {
        return EmployeeOfficeHours::where('employee_id', '=', User::logged()->employee->id)->paginate(15);
    }

    public function employee() {
        return $this->belongsTo('App\Employee', 'employee_id');
    }
}
