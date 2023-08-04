<?php

namespace App\Http\Controllers;

use App\JobLevel;
use Illuminate\Http\Request;

class JobLevelController extends Controller
{
    public static function all() {
        return JobLevel::all();
    }

    public static function filter($query) {
        return JobLevel::filter($query);
    }
}
