<?php

namespace App\Http\Controllers;

use App\BriefingSpecialPresentation;
use Illuminate\Http\Request;

class BriefingSpecialPresentationController extends Controller
{
    public static function all() {
        return BriefingSpecialPresentation::all();
    }

    public static function filter($query) {
        return BriefingSpecialPresentation::filter($query);
    }
}
