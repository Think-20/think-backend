<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DateTime;
use DateInterval;

class Briefing extends Model {
    
    public $timestamps = false;
    protected $table = 'briefing';
    protected $fillable = [
        'job_id', 'responsible_id', 'available_date', 'estimated_time'
    ];

    public static function loadForm() {
        $responsibles = Employee::whereHas('department', function($query) {
            $query->where('description', '=', 'Criação');
        })->get();

        $now = new DateTime('now');
        $activityList = Briefing::where('available_date', '>=', DateHelper::subUtil($now, 10)->format('Y-m-d'))
        ->where('available_date' , '<=', DateHelper::sumUtil($now, 30)->format('Y-m-d'))
        ->orderBy('available_date', 'ASC')
        ->orderBy('responsible_id', 'ASC')
        ->limit(30)
        ->get();

        $arr = ActivityHelper::calculateNextDate($now->format('Y-m-d'), $responsibles, 1, $activityList);

        return [
            'responsibles' => $responsibles,
            'available_date' => ($arr['date'])->format('Y-m-d'),
            'responsible' => $arr['responsible'],
            'presentations' => BriefingPresentation::all(),
        ];
    }

    public static function recalculateNextDate($estimatedTime) {
        $responsibles = Employee::whereHas('department', function($query) {
            $query->where('description', '=', 'Criação');
        })->get();

        $now = new DateTime('now');
        $activityList = Briefing::where('available_date', '>=', DateHelper::subUtil($now, 10)->format('Y-m-d'))
        ->where('available_date' , '<=', DateHelper::sumUtil($now, 30)->format('Y-m-d'))
        ->orderBy('available_date', 'ASC')
        ->orderBy('responsible_id', 'ASC')
        ->limit(30)
        ->get();

        $arr = ActivityHelper::calculateNextDate($now->format('Y-m-d'), $responsibles, $estimatedTime, $activityList);

        return [
            'available_date' => ($arr['date'])->format('Y-m-d'),
            'responsible' =>  $arr['responsible']
        ];
    }
    
    public static function editAvailableDate(array $data) {
        $id = $data['id'];
        $briefing = Briefing::find($id);
        $available_date = isset($data['available_date']) ? $data['available_date'] : null;
        $briefing->update(['available_date' => $available_date]);
        return $briefing;
    }

    public static function myEditAvailableDate(array $data) {
        $id = $data['id'];
        $briefing = Briefing::find($id);
        $available_date = isset($data['available_date']) ? $data['available_date'] : null;

        if($briefing->job->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para editar esse job.');
        }

        $briefing->update(['available_date' => $available_date]);
        return $briefing;
    }

    public static function insert(array $data) {
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        $job_id = isset($data['job']['id']) ? $data['job']['id'] : null;
        $briefing = new Briefing(array_merge($data, [
            'responsible_id' => $responsible_id,
            'job_id' => $job_id
        ]));

        $briefing->save();
        //$briefing->saveChild($data);  

        $arrayPresentations = !isset($data['presentations']) ? [] : $data['presentations'];
        $briefing->savePresentations($arrayPresentations);

        return $briefing;
    }

    public static function edit(array $data) {
        $id = $data['id'];
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        $briefing = Briefing::find($id);

        $briefing->update(
            array_merge($data, [
                'responsible_id' => $responsible_id
            ])
        );  

        //$briefing->editChild($data);       

        $arrayPresentations = !isset($data['presentations']) ? [] : $data['presentations'];
        $briefing->savePresentations($arrayPresentations); 
    }

    public function get() {
        $this->presentations;
        $this->responsible;
    }

    public function savePresentations(array $data) {
        $this->presentations()->detach();

        foreach($data as $presentation) {
            $this->presentations()->attach($presentation['id']);
        }
    }

    public function saveChild($data) {
        if($this->job_type->description === 'Stand') {
            Stand::insert($this, $data['stand']);
        }
    }
 
    public function saveFilesChild($data) {
        if($this->job_type->description === 'Stand') {
            $this->stand->saveFiles($data['stand']);
        }
    }
 
    public function editChild($data) {
        if($this->job_type->description === 'Stand') {
            Stand::edit($data['stand']);
        }
    }
 
    public function editFilesChild($oldChild, $data) {
        if($this->job_type->description === 'Stand') {
            $this->stand->editFiles($oldChild, $data['stand']);
        }
    }
 
    public function deleteChild() {
        if($this->job_type->description === 'Stand') {
            Stand::remove($this->stand->id);
        }
    }
 
    public static function getBriefingChild(Briefing $job) {
        if($job->job_type->description === 'Stand') {
            $stand = $job->stand;
            $stand->job;
            $stand->configuration;
            $stand->genre;
            $stand->column = $stand->column == 0 ? ['id' => 0] : ['id' => 1];
            $items = $stand->items;
            foreach($items as $item) {
                $item->type;
            }

            return $stand;
        }
    }

    public function presentations() {
        return $this->belongsToMany('App\BriefingPresentation', 'briefing_presentation_briefing', 'briefing_id', 'presentation_id');
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
