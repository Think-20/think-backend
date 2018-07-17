<?php

namespace App\Http\Controllers;

use App\JobActivity;
use Illuminate\Http\Request;

class JobActivityController extends Controller
{
    public static function all() {
        return JobActivity::all();
    }

    public static function filter($query) {
        return JobActivity::filter($query);
    }
}
