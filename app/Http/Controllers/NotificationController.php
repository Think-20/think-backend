<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Notification;

class NotificationController extends Controller
{
    public static function read(Request $request) {
        $status = false;

        try {
            UserNotification::read($request->all());
            $message = 'Notificações marcadas como lidas.';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao atualizar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }
    
    public static function all() {
        return UserNotification::list();
    }
    
    public static function recents() {
        return UserNotification::recents();
    }

    public static function listen() {
        return UserNotification::listen();
    }
}
