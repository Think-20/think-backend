<?php

namespace App\Http\Controllers;

use App\BriefingPresentation;
use Illuminate\Http\Request;

class BriefingPresentationController extends Controller
{
    public static function all() {
        return BriefingPresentation::all();
    }

    public static function filter($query) {
        return BriefingPresentation::filter($query);
    }
}
