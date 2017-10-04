<?php

namespace App\Http\Controllers;

use App\PersonType;
use Illuminate\Http\Request;

class PersonTypeController extends Controller
{
    public static function all() {
        return PersonType::all();
    }
}
