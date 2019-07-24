<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class JobActivity extends Model
{
    protected $table = 'job_activity';

    protected $fillable = [
        'description', 'show', 'no_params', 'only_edit', 'redirect_after_save',
        'fixed_duration', 'min_duration', 'max_duration', 'max_budget_value_per_day',
        'max_duration_value_per_day'
    ];

    public static function getOpportunities() {
        return JobActivity::whereIn('description', ['Projeto'])->get();
    }

    public static function getOpportunitiesAndOthers() {
        return JobActivity::whereIn('description', ['Projeto', 'Orçamento', 'Outsider'])->get();
    }

    public function responsibles() {
        return $this->belongsToMany('App\Employee', 'job_activity_employee')->with('user');
    }
}
