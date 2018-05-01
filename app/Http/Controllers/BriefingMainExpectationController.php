<?php

namespace App\Http\Controllers;

use App\BriefingMainExpectation;
use Illuminate\Http\Request;

class BriefingMainExpectationController extends Controller
{
    public static function all() {
        return BriefingMainExpectation::all();
    }

    public static function filter($query) {
        return BriefingMainExpectation::filter($query);
    }
}
