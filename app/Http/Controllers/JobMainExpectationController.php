<?php

namespace App\Http\Controllers;

use App\JobMainExpectation;
use Illuminate\Http\Request;

class JobMainExpectationController extends Controller
{
    public static function all() {
        return JobMainExpectation::all();
    }

    public static function filter($query) {
        return JobMainExpectation::filter($query);
    }
}
