<?php

namespace App\Http\Controllers;

use App\BriefingStatus;
use Illuminate\Http\Request;

class BriefingStatusController extends Controller
{
    public static function all() {
        return BriefingStatus::all();
    }

    public static function filter($query) {
        return BriefingStatus::filter($query);
    }
}
