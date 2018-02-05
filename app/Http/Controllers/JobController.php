<?php

namespace App\Http\Controllers;

use App\Job;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public static function all() {
        return Job::all();
    }

    public static function filter($query) {
        return Job::filter($query);
    }
}
