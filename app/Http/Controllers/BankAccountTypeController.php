<?php

namespace App\Http\Controllers;

use App\BankAccountType;
use Illuminate\Http\Request;

class BankAccountTypeController extends Controller
{
    public static function all() {
        return BankAccountType::all();
    }
}
