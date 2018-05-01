<?php

namespace App\Http\Controllers;

use App\BriefingLevel;
use Illuminate\Http\Request;

class BriefingLevelController extends Controller
{
    public static function all() {
        return BriefingLevel::all();
    }

    public static function filter($query) {
        return BriefingLevel::filter($query);
    }
}
