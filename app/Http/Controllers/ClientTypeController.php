<?php

namespace App\Http\Controllers;

use App\ClientType;
use Illuminate\Http\Request;

class ClientTypeController extends Controller
{
    public static function all() {
        return ClientType::all();
    }
}
