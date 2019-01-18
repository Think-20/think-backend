<?php

namespace App\Http\Controllers;

use App\User; 
use Session;
use Response;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public static function all() {
        return User::list();
    }

    public static function filter(Request $request) {
        return User::filter($request->all());
    }

    public static function get(int $id) {
        return User::get($id);
    }

    public static function save(Request $request) {
        $status = false;

        try {
            $user = User::insert($request->all());
            $message = 'Usuário cadastrado com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao cadastrar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public static function edit(Request $request) {
        $status = false;

        try {
            User::edit($request->all());
            $message = 'Usuário alterado com sucesso!';
            $status = true;
        } catch(Exception $e) {
            $message = 'Um erro desconhecido ocorreu ao atualizar: ' . $e->getMessage();
        }

        return Response::make(json_encode([
            'message' => $message,
            'status' => $status,
         ]), 200);
    }

    public function login(Request $request) {
        $email = $request->input('email');
        $password = $request->input('password');

        $user = User::auth($email, $password);

        if(!$user) {
            return [
                'user' => null,
                'token' => null
            ];
        }

        return [
            'token' => User::generateToken($user),
            'user' => $user
        ];
    }

    public function logout(Request $request) {
        $userId = $request->header('User');
        $token = $request->header('Authorization');

        User::logout($userId, $token);
    }
}
