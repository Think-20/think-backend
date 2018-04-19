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
        'autoExitPlace', 'autoExitPlaceCoordinates', 'entry_place_id', 'exit_place_id'
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

    public static function statusYourself() {
        $employee = User::logged()->employee;
        return Timecard::whereNull('exit')
        ->where('employee_id', '=', $employee->id)
        ->get();
    }

    public static function registerYourself(array $data) {
        $approved = 0;
        $employee = User::logged()->employee;
        $place_id = isset($data['place_id']['id']) ? $data['place_id']['id'] : null;
        $place = isset($data['place']) ? $data['place'] : null;
        $coordinates = isset($data['coordinates']) ? $data['coordinates'] : null;
        $timecard = Timecard::whereNull('exit')
        ->where('employee_id', '=', $employee->id)
        ->first();
        $timecardDuplicated = Timecard::where('entry','>=', (new DateTime('now'))->format('y-m-d'))
        ->where('entry','<=', (new DateTime('now'))->format('y-m-d') . ' 23:59:59')
        ->where('employee_id', '=', $employee->id)
        ->whereNotNull('exit')
        ->first();
        $autoPlace = GoogleApi::getAutoPlace($coordinates);

        
        if($place_id == null) {
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
                    'entry_place_id' => $place_id,
                    'entryPlace' => $place,
                    'autoEntryPlaceCoordinates' => $coordinates,
                    'autoEntryPlace' => $autoPlace
                ])
            );

            $timecard->save();
        } else {
            $reason = isset($data['reason']) ? $data['reason'] : null;
            $timecard->exit = (new DateTime('now'))->format('Y-m-d H:i:s');
            $seconds2 = Timecard::diffInSeconds($timecard);
            $seconds = Timecard::discount($seconds2);

            if($seconds > 28800 && empty($reason)) {
                throw new \Exception('Você precisa justificar a diferença de horário.');
            } else if($seconds > 28800 && !empty($reason)) {
                $approved = 0;
            } else {
                $approved = 1;
            }

            $timecard->update(
                [
                    'exit' => $timecard->exit->format('Y-m-d H:i:s'),
                    'reason' => $reason,
                    'approved' => $approved,
                    'exit_place_id' => $place_id,
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
        $placeId = isset($data['place']['id']) ? $data['place']['id'] : null;
        $month = isset($data['month']['id']) ? $data['month']['id'] : null;
        $year = isset($data['year']) ? $data['year'] : null;
        
        if($employeeId == null) {
            return;
        }

        $timecards = Timecard::where('entry', '>=', $year . '-' . $month . '-01 00:00:00')
        ->where('entry', '<=', $year . '-' . $month . '-31 23:59:59');
        $timecards->where('employee_id', '=', $employeeId);

        if($placeId != null) {
            $timecards->where(function($query) use ($placeId) {
                $query->where('entry_place_id', '=', $placeId)
                ->orWhere('exit_place_id', '=', $placeId);
            });
        }

        $timecards = $timecards->get();

        foreach($timecards as $timecard) {
            if($timecard->approved_user != null) $timecard->approved_user->employee;
            $timecard->entry_place;
            $timecard->exit_place;
        }

        return $timecards;
    }

    public static function showYourself(array $data) {
        $placeId = isset($data['place']['id']) ? $data['place']['id'] : null;
        $employeeId = User::logged()->employee->id;
        $month = isset($data['month']['id']) ? $data['month']['id'] : null;
        $year = isset($data['year']) ? $data['year'] : null;

        if($year == null || $month == null) {
            return;
        }

        $timecards = Timecard::where('employee_id', '=', $employeeId)
        ->where('entry', '>=', $year . '-' . $month . '-01 00:00:00')
        ->where('entry', '<=', $year . '-' . $month . '-31 23:59:59');
        

        if($placeId != null) {
            $timecards->where(function($query) use ($placeId) {
                $query->where('entry_place_id', '=', $placeId)
                ->orWhere('exit_place_id', '=', $placeId);
            });
        }

        $timecards = $timecards->get();

        foreach($timecards as $timecard) {
            if($timecard->approved_user != null) $timecard->approved_user->employee;
            $timecard->entry_place;
            $timecard->exit_place;
            if($timecard->exit != null) {
                $timecard->balance = Timecard::secondsToHourMin(Timecard::discount(Timecard::diffInSeconds(($timecard))), false);
            }
        }

        return $timecards;
    }

    public static function discount($seconds) {
        /*
        [17:18, 18/4/2018] Hugo Morales: mas juro que prefiro descontar direto...
        [17:19, 18/4/2018] Hugo Morales: deu mais de 6:00 horas, desconta 1:00
        deu de 4 á 6, desconta 30 min
        [17:20, 18/4/2018] Hugo Morales: fez de mais de 12:00 desconta mais total 1:30
        [17:20, 18/4/2018] Hugo Morales: fez mais de 14:00 desconta 2:00
        */
        $hours = $seconds / 3600;
        
        if($hours >= 4 && $hours <= 6) {
            $seconds = $seconds - (30 * 60);
        }
        else if($hours > 6 && $hours <= 12) {
            $seconds = $seconds - (60 * 60);
        } 
        else if($hours > 12 && $hours <= 14) {
            $seconds = $seconds - (90 * 60);
        }
        else if($hours > 14) {
            $seconds = $seconds - (120 * 60);        
        }

        return $seconds;
    }

    public static function diffInSeconds(Timecard $timecard) {  
        $exit = new DateTime($timecard->exit);
        $interval = $exit->diff(new DateTime($timecard->entry));
        $hour = $interval->h;
        $min = $interval->i;

        $seconds = ($interval->s)
        + ($interval->i * 60)
        + ($interval->h * 60 * 60)
        + ($interval->d * 60 * 60 * 24)
        + ($interval->m * 60 * 60 * 24 * 30)
        + ($interval->y * 60 * 60 * 24 * 365);
        
        return $seconds;
    }

    public static function secondsToHourMin($seconds, $withSign = true) {
        $sign = '+';

        if($seconds < 0) {
            $sign = '-';
            $seconds = $seconds * - 1;
        }
            
        $hours = floor($seconds / 3600);
        $min = floor(($seconds - ($hours * 3600)) / 60);

        if(strlen($hours) == 1) { 
            $hours = '0' . $hours;
        }

        if(strlen($min) == 1) { 
            $min = '0' . $min;
        }
        return $withSign ? $sign . $hours . ':' . $min : $hours . ':' . $min;
    }

    public static function balance($employeeId) {
        $timecards = Timecard::where('employee_id', '=', $employeeId)
        ->whereNotNull('exit')
        ->get();
        $balance = 0;

        foreach($timecards as $timecard) {
            $seconds2 = Timecard::diffInSeconds($timecard);
            $seconds = Timecard::discount($seconds2);
            $balance += $seconds;
        }

        return Timecard::secondsToHourMin($balance);
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

    public function entry_place() {
        return $this->belongsTo('App\TimecardPlace', 'entry_place_id');
    }

    public function exit_place() {
        return $this->belongsTo('App\TimecardPlace', 'exit_place_id');
    }

    public function employee() {
        return $this->belongsTo('App\Employee', 'employee_id');
    }

    public function approved_user() {
        return $this->belongsTo('App\User', 'approved_by');
    }
}
