<?php

namespace App\Http\Controllers;

use App\TimecardPlace;
use Illuminate\Http\Request;

class TimecardPlaceController extends Controller
{
    public static function all() {
        return TimecardPlace::all();
    }
}
