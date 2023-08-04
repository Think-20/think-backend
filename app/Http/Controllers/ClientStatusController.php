<?php

namespace App\Http\Controllers;

use App\ClientStatus;
use Illuminate\Http\Request;

class ClientStatusController extends Controller
{
    public static function all() {
        return ClientStatus::all();
    }
}
