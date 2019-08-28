<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class JobActivity extends Model
{
    protected $table = 'job_activity';

    protected $fillable = [
        'description', 'initial', 'no_params', 'redirect_after_save',
        'fixed_duration', 'min_duration', 'max_duration', 'max_budget_value_per_day',
        'max_duration_value_per_day', 'modify_id', 'option_id', 'fixed_budget_value'
    ];

    public static function list() {
        return JobActivity::with('modify', 'option')->get();
    }

    public static function getOpportunities() {
        return JobActivity::whereIn('description', ['Projeto'])->get();
    }

    public static function getOpportunitiesAndOthers() {
        return JobActivity::whereIn('description', ['Projeto', 'Orçamento', 'Outsider'])->get();
    }

    public function responsibles() {
        return $this->belongsToMany('App\Employee', 'job_activity_employee')->with('user');
    }

    public function modify() {
        return $this->belongsTo('App\JobActivity', 'modify_id');
    }

    public function option() {
        return $this->belongsTo('App\JobActivity', 'option_id');
    }

    public function share_budget() {
        return $this->hasMany('App\JobActivityShareBudget', 'from_id');
    }

    public function share_duration() {
        return $this->hasMany('App\JobActivityShareDuration', 'from_id');
    }
}
