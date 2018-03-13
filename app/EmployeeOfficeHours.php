<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmployeeOfficeHours extends Model
{
    public $timestamps = false;

    protected $table = 'employee_office_hours';

    protected $fillable = [
        'entry', 'exit', 'employee_id', 'reason', 'approved'
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

    public static function registerYourself(array $data) {
        $approved = 0;
        $employee = User::logged()->employee;
        $dateEntry = new \DateTime($data['entry']);
        $dateExit = new \DateTime($data['exit']);
        $reason = isset($data['reason']) ? $data['reason'] : null;

        $testIfExists = EmployeeOfficeHours::where('employee_id', '=', $employee->id)
        ->where('entry', '>=', $dateEntry->format('Y-m-d'))
        ->where('entry', '<=', $dateEntry->format('Y-m-d') . ' 23:59:59')
        ->count();

        if($testIfExists > 0) {
            throw new \Exception('Você já registrou nessa data.');
        }

        $interval = $dateExit->diff($dateEntry);
        $hour = $interval->h;
        $min = $interval->i;

        $seconds = ($interval->s)
         + ($interval->i * 60)
         + ($interval->h * 60 * 60)
         + ($interval->d * 60 * 60 * 24)
         + ($interval->m * 60 * 60 * 24 * 30)
         + ($interval->y * 60 * 60 * 24 * 365);

        if(($seconds > 33300 || $seconds < 31500) && empty($reason)) {
            throw new \Exception('Você precisa justificar a diferença de horário.');
        } else if($seconds > 33300 || $seconds < 31500) {
            $approved = 0;
        } else {
            //Aprovar, horário comercial normal
            $approved = 1;
        }
        
        $officeHours = EmployeeOfficeHours::create(
            array_merge($data, [
                'employee_id' => $employee->id,
                'reason' => $reason,
                'approved' => $approved
            ])
        );

        $officeHours->save();

        return $officeHours;
    }

    public static function showAnother($employeeId) {
        return EmployeeOfficeHours::where('employee_id', '=', $employeeId)->paginate(15);
    }

    public static function showYourself() {
        return EmployeeOfficeHours::where('employee_id', '=', User::logged()->employee->id)->paginate(15);
    }

    public static function showApprovalsPending() {
        return EmployeeOfficeHours::where('approved', '=', '0')->get();
    }

    public static function approvePending($id) {
        $officeHours = EmployeeOfficeHours::find($id);

        if($officeHours == null) {
            throw new \Exception('Informe corretamente o horário.');
        }
        
        $officeHours->update(['approved' => 1]);
        
        return $officeHours;
    }

    public function employee() {
        return $this->belongsTo('App\Employee', 'employee_id');
    }
}
