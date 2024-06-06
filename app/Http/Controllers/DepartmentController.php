<?php

namespace App\Http\Controllers;

use App\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public static function all() {
        return Department::list();
    }
    
    public static function filter(Request $request) {
        return Department::list($request->all());
    }
}
