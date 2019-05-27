<?php

namespace App\Http\Controllers;

use App\JobActivity;
use Illuminate\Http\Request;

class JobActivityController extends Controller
{
    public static function all() {
        return JobActivity::where('show', '=', 1)
        ->orderBy('description', 'asc')
        ->get();
    }

    public static function filter($query) {
        return JobActivity::filter($query);
    }
}
