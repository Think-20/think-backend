<?php

namespace App\Http\Controllers;

use App\JobHowCome;
use Illuminate\Http\Request;

class JobHowComeController extends Controller
{
    public static function all() {
        return JobHowCome::all();
    }

    public static function filter($query) {
        return JobHowCome::filter($query);
    }
}
