<?php

namespace App\Http\Controllers;

use App\JobCompetition;
use Illuminate\Http\Request;

class JobCompetitionController extends Controller
{
    public static function all() {
        return JobCompetition::all();
    }

    public static function filter($query) {
        return JobCompetition::filter($query);
    }
}
