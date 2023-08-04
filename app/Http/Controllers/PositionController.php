<?php

namespace App\Http\Controllers;

use App\Position;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public static function all() {
        return Position::list();
    }
    
    public static function filter(Request $request) {
        return Position::list($request->all());
    }
}
