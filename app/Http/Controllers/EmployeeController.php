<?php

namespace App\Http\Controllers;

use App\Employee;
use Illuminate\Http\Request;
use Response;
use Exception;

class EmployeeController extends Controller
{
    public static function all() {
        return Employee::list();
    }

    public static function filter($query) {
        return Employee::filter($query);
    }

    public static function canInsertClients() {
        return Employee::canInsertClients();
    }
}
