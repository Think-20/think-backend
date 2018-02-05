<?php

namespace App\Http\Controllers;

use App\BriefingCompetition;
use Illuminate\Http\Request;

class BriefingCompetitionController extends Controller
{
    public static function all() {
        return BriefingCompetition::all();
    }

    public static function filter($query) {
        return BriefingCompetition::filter($query);
    }
}
