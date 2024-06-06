<?php

namespace App\Http\Controllers;

use App\ClientComission;
use Illuminate\Http\Request;

class ClientComissionController extends Controller
{
    public static function all() {
        return ClientComission::all();
    }
}
