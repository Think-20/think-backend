<?php

namespace App\Http\Controllers;
use Exception;
use Response;

use Illuminate\Http\Request;

class UploadFileController extends Controller
{
    public static function upload(Request $request) {
        $file = $request->file('file');
        $file->move(sys_get_temp_dir(), $file->getClientOriginalName());
        return ['name' => $file->getClientOriginalName()];
    }
}
