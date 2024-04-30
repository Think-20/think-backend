<?php

namespace App\Http\Controllers;

use Exception;
use Response;

use Illuminate\Http\Request;

class UploadFileController extends Controller
{
    public static function upload(Request $request)
    {
        $names = [];
        $files = $request->all();

        foreach ($files as $file) {

            /*$normalizedName = str_ireplace(
                array('\'', '"', ',', ';', '<', '>', '-', '_', '0', '1', '2', '3', '4', '6', '5', '7', '8', '9'),
                '',
                $file->getClientOriginalName()
            );*/

            $file->move(sys_get_temp_dir(), $file->getClientOriginalName());
            $names[] = $file->getClientOriginalName();
        }

        return Response::make(json_encode(['names' => $names]), 200);
    }
}
