<?php

namespace App\Http\Controllers;

use App\StandGenre;
use Illuminate\Http\Request;

class StandGenreController extends Controller
{
    public static function all() {
        return StandGenre::all();
    }

    public static function filter($query) {
        return StandGenre::filter($query);
    }
}
