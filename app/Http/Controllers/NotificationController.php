<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Response;

class NotificationController extends Controller
{
    /**
     * Desativado temporariamente: nenhuma notificacao e devolvida a nenhum usuario.
     * Remover este short-circuit quando as notificacoes voltarem a ser usadas.
     */
    private static function emptyList()
    {
        return [
            'pagination' => [
                'data' => [],
            ],
            'updatedInfo' => [],
        ];
    }

    public static function read(Request $request)
    {
        return Response::make(json_encode([
            'message' => 'Notificações marcadas como lidas.',
            'status' => true,
        ]), 200);
    }

    public static function all()
    {
        return self::emptyList();
    }

    public static function recents()
    {
        return self::emptyList();
    }

    public static function listen()
    {
        return [];
    }

    public static function window()
    {
        return [];
    }

    public static function windowCheckin()
    {
        return [];
    }
}
