<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JobActivity extends Model
{
    protected $table = 'job_activity';

    protected $fillable = [
        'description'
    ];

    public static function getOpportunities() {
        return JobActivity::whereIn('description', ['Projeto'])->get();
    }

    public static function getOpportunitiesAndOthers() {
        return JobActivity::whereIn('description', ['Projeto', 'Orçamento', 'Outsider'])->get();
    }
}
