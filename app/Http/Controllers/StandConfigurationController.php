<?php

namespace App\Http\Controllers;

use App\StandConfiguration;
use Illuminate\Http\Request;

class StandConfigurationController extends Controller
{
    public static function all() {
        return StandConfiguration::all();
    }

    public static function filter($query) {
        return StandConfiguration::filter($query);
    }
}
