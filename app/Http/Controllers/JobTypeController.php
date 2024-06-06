<?php

namespace App\Http\Controllers;

use App\JobType;
use Illuminate\Http\Request;

class JobTypeController extends Controller
{
    public static function all() {
        return JobType::all();
    }

    public static function filter($query) {
        return JobType::filter($query);
    }
}
