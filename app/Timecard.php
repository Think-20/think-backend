<?php

namespace App;

use DateTime;
use Illuminate\Database\Eloquent\Model;

class Timecard extends Model
{
    public $timestamps = false;

    protected $table = 'timecard';

    protected $fillable = [
        'entry', 'exit', 'employee_id', 'reason', 'approved', 'approved_by',
        'entryPlace', 'exitPlace', 'autoEntryPlace', 'autoEntryPlaceCoordinates',
        'autoExitPlace', 'autoExitPlaceCoordinates'
    ];

    /*
    protected $dates = [
        'entry', 'exit'
    ];
    */

    public static function edit(array $data) {
        $id = isset($data['id']) ? (int) $data['id'] : null;
        $employeeId = isset($data['employee']['id']) ? (int) $data['employee']['id'] : null;

        if($employeeId == null || ($employee = Employee::find($employeeId)) == null) {
            throw new \Exception('Informe corretamente o funcionário.');
        }
        

        if($id == null) {
            throw new \Exception('Informe corretamente o horário.');
        }
    
        if(isset($data['reason'])) {
            unset($data['reason']);
        } 

        $officeHour = Timecard::find($id);
        $officeHour->update(array_merge($data, [
            'employee_id' => $employee->id
        ]));
    }

    public static function remove(int $id) {
        if($id == null) {
            throw new \Exception('Informe corretamente o horário.');
        }

        $officeHour = Timecard::find($id);
        $officeHour->delete();
    }

    public static function get($id) {
        $officeHours = Timecard::find($id);
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
            $officeHours = Timecard::create(
                array_merge($data, [
                    'employee_id' => $employee->id
                ])
            );
            $officeHours->save();
        } else {
            $officeHours = Timecard::find($id);
            if($officeHours == null) {
                throw new \Exception('Informe corretamente o horário que deseja alterar.');
            }
            $officeHours->update($data);
        }

        return $officeHours;
    }

    /*
    public static function registerYourself(array $data) {
        $approved = 0;
        $employee = User::logged()->employee;
        $dateEntry = new \DateTime($data['entry']);
        $dateExit = new \DateTime($data['exit']);
        $reason = isset($data['reason']) ? $data['reason'] : null;

        $testIfExists = Timecard::where('employee_id', '=', $employee->id)
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
        
        $officeHours = Timecard::create(
            array_merge($data, [
                'employee_id' => $employee->id,
                'reason' => $reason,
                'approved' => $approved
            ])
        );

        $officeHours->save();

        return $officeHours;
    }
    */

    public static function statusYourself() {
        $employee = User::logged()->employee;
        return Timecard::whereNull('exit')->get();
    }

    public static function registerYourself(array $data) {
        $approved = 0;
        $employee = User::logged()->employee;
        $place = isset($data['place']) ? $data['place'] : null;
        $coordinates = isset($data['coordinates']) ? $data['coordinates'] : null;
        $timecard = Timecard::whereNull('exit')->first();
        $timecardDuplicated = Timecard::where('entry','>=', (new DateTime('now'))->format('y-m-d'))
        ->where('entry','<=', (new DateTime('now'))->format('y-m-d') . ' 23:59:59')
        ->whereNotNull('exit')
        ->first();
        $autoPlace = GoogleApi::getAutoPlace($coordinates);

        
        if($place == null) {
            throw new \Exception('Você deve informar um local.');
        }
        
        if($coordinates == null) {
            throw new \Exception('Coordenadas não detectadas.');
        }
        
        if(!$timecardDuplicated == null) {
            throw new \Exception('Você já informou checkout para esse dia. Se for necessário, solicite a correção.');
        }

        if($timecard == null) {
            $timecard = Timecard::create(
                array_merge($data, [
                    'entry' => (new DateTime('now'))->format('Y-m-d H:i:s'),
                    'employee_id' => $employee->id,
                    'approved_by' => null,
                    'entryPlace' => $place,
                    'autoEntryPlaceCoordinates' => $coordinates,
                    'autoEntryPlace' => $autoPlace
                ])
            );

            $timecard->save();
        } else {
            $reason = isset($data['reason']) ? $data['reason'] : null;
            $timecard->exit = new DateTime('now');
            $interval = $timecard->exit->diff(new DateTime($timecard->entry));

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
            } else if(($seconds > 33300 || $seconds < 31500) && !empty($reason)) {
                $approved = 0;
            } else {
                //Aprovar, horário comercial normal
                $approved = 1;
            }

            $timecard->update(
                [
                    'exit' => (new DateTime('now'))->format('Y-m-d H:i:s'),
                    'reason' => $reason,
                    'approved' => $approved,
                    'exitPlace' => $place,
                    'autoExitPlaceCoordinates' => $coordinates,
                    'autoExitPlace' => $autoPlace
                ]
            );

            $timecard->save();
        }

        return $timecard;
    }

    public static function showAnother(array $data) {
        $employeeId = isset($data['employee']['id']) ? $data['employee']['id'] : null;
        $month = isset($data['month']['id']) ? $data['month']['id'] : null;
        $year = isset($data['year']) ? $data['year'] : null;


        if($year == null || $month == null || $employeeId == null) {
            return;
        }

        $timecards = Timecard::where('employee_id', '=', $employeeId)
        ->where('entry', '>=', $year . '-' . $month . '-01 00:00:00')
        ->where('entry', '<=', $year . '-' . $month . '-31 23:59:59')
        ->get();

        foreach($timecards as $timecard) {
            if($timecard->approved_user != null) $timecard->approved_user->employee;
        }

        return $timecards;

        /*
        if($year == null || $month == null) {
            return;
        }

        $query = Timecard::where('entry', '>=', $year . '-' . $month . '-01 00:00:00')
        ->where('entry', '<=', $year . '-' . $month . '-31 23:59:59');
        
        if($employeeId != null) {
            $query->where('employee_id', '=', $employeeId);
        }

        return $query->get();
        */
    }

    public static function showYourself(array $data) {
        $employeeId = User::logged()->employee->id;
        $month = isset($data['month']['id']) ? $data['month']['id'] : null;
        $year = isset($data['year']) ? $data['year'] : null;

        if($year == null || $month == null) {
            return;
        }

        $timecards = Timecard::where('employee_id', '=', $employeeId)
        ->where('entry', '>=', $year . '-' . $month . '-01 00:00:00')
        ->where('entry', '<=', $year . '-' . $month . '-31 23:59:59')
        ->get();

        foreach($timecards as $timecard) {
            if($timecard->approved_user != null) $timecard->approved_user->employee;
        }

        return $timecards;
    }

    public static function showApprovalsPending() {
        return Timecard::where('approved', '=', '0')->get();
    }

    public static function approvePending($id) {
        $officeHours = Timecard::find($id);
        $user = User::logged();

        if($officeHours == null) {
            throw new \Exception('Informe corretamente o horário.');
        }
        
        $officeHours->update(['approved' => 1, 'approved_by' => $user->id]);
        
        return $officeHours;
    }

    public function employee() {
        return $this->belongsTo('App\Employee', 'employee_id');
    }

    public function approved_user() {
        return $this->belongsTo('App\User', 'approved_by');
    }
}
