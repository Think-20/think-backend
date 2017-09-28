<?php

namespace App\Http\Controllers;

use App\User; 
use Session;

use Illuminate\Http\Request;

class UserController extends Controller
{
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
