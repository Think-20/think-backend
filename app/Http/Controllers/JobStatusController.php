<?php

namespace App\Http\Controllers;

use App\JobStatus;
use Illuminate\Http\Request;

class JobStatusController extends Controller
{
    public static function all() {
        return JobStatus::all();
    }

    public static function filter($query) {
        return JobStatus::filter($query);
    }
}
