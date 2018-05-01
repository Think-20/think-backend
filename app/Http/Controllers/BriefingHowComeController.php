<?php

namespace App\Http\Controllers;

use App\BriefingHowCome;
use Illuminate\Http\Request;

class BriefingHowComeController extends Controller
{
    public static function all() {
        return BriefingHowCome::all();
    }

    public static function filter($query) {
        return BriefingHowCome::filter($query);
    }
}
