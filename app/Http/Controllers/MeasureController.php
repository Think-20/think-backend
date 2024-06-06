<?php

namespace App\Http\Controllers;

use App\Measure;
use Illuminate\Http\Request;

class MeasureController extends Controller
{
    public static function all() {
        return Measure::list();
    }

    public static function filter($query) {
        return Measure::filter($query);
    }
}
