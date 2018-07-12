<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DateTime;
use DateInterval;

class Budget extends Model {
    
    public $timestamps = false;
    protected $table = 'budget';
    protected $fillable = [
        'job_id', 'responsible_id', 'available_date',
    ];

    public static function loadForm() {
        $responsibles = Employee::where('name', 'LIKE', 'Rafaela%')->get();
        $now = new DateTime('now');
        $activityList = Budget::where('available_date', '>=', DateHelper::subUtil($now, 10)->format('Y-m-d'))
        ->where('available_date' , '<=', DateHelper::sumUtil($now, 30)->format('Y-m-d'))
        ->orderBy('available_date', 'ASC')
        ->orderBy('responsible_id', 'ASC')
        ->limit(30)
        ->get();

        $arr = ActivityHelper::calculateNextDate($now->format('Y-m-d'), $responsibles, 1, $activityList);

        return [
            'responsibles' => $responsibles,
            'available_date' => ($arr['date'])->format('Y-m-d'),
            'responsible' => $arr['responsible']
        ];
    }

    public function remove() {
        $this->delete();
    }

    public static function getNextAvailableDate($availableDate, $estimatedTime, $swap) {
        $responsibles = Employee::where('name', 'LIKE', 'Rafaela%')->get();
        $date = new DateTime($availableDate);
        $activityList = Budget::where('available_date', '>=', DateHelper::subUtil($date, 10)->format('Y-m-d'))
        ->where('available_date' , '<=', DateHelper::sumUtil($date, 30)->format('Y-m-d'))
        ->orderBy('available_date', 'ASC')
        ->orderBy('responsible_id', 'ASC')
        ->limit(30)
        ->get();

        if($swap) {
            $activityList = $activityList->reject(function ($model) use ($availableDate) {
                return $model->available_date == $availableDate;
            });
        }

        $arr = ActivityHelper::calculateNextDate($date->format('Y-m-d'), $responsibles, $estimatedTime, $activityList);

        return [
            'available_date' => ($arr['date'])->format('Y-m-d'),
            'responsible' =>  $arr['responsible']
        ];
    }

    public static function editAvailableDate(array $data) {
        $id = $data['id'];
        $budget = Budget::find($id);
        $available_date = isset($data['available_date']) ? $data['available_date'] : null;
        $budget->update(['available_date' => $available_date]);
        return $budget;
    }

    public static function myEditAvailableDate(array $data) {
        $id = $data['id'];
        $budget = Budget::find($id);
        $available_date = isset($data['available_date']) ? $data['available_date'] : null;

        if($budget->job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para editar esse job.');
        }

        $budget->update(['available_date' => $available_date]);
        return $budget;
    }

    public static function insert(array $data) {
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        $job_id = isset($data['job']['id']) ? $data['job']['id'] : null;
        $budget = new Budget(array_merge($data, [
            'responsible_id' => $responsible_id,
            'job_id' => $job_id
        ]));

        $budget->save();
    }

    public static function edit(array $data) {
        $id = $data['id'];
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        $budget = Budget::find($id);

        $budget->update(
            array_merge($data, [
                'responsible_id' => $responsible_id
            ])
        );  
    }

    public function get() {
        $this->responsible;
    }

    public function responsible() {
        return $this->belongsTo('App\Employee', 'responsible_id');
    }

    public function setEstimatedTimeAttribute($value) {
        $this->attributes['estimated_time'] = (float) str_replace(',', '.', $value);
    }

    public function setEstimated_timeAttribute($value) {
        $this->attributes['estimated_time'] = (float) str_replace(',', '.', $value);
    }
}
