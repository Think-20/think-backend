<?php

namespace App\Http\Middleware;

use App\User;
use Closure;
use Request;
use Response;

class ApiAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $userId = empty($request->header('User')) ? $request->input('user_id') : (int) $request->header('User');
        $token = empty($request->header('Authorization')) ? $request->input('access_token') : $request->header('Authorization');

        if(empty($userId) || empty($token)) {
            if(Request::isJson()) {
                $content = json_encode(['message' => 'Por favor, forneça sua chave de autenticação.']);
            } else {
                $content = 'Por favor, forneça sua chave de autenticação.';
            }
            return Response::make($content, 403);
        }

        $currentUser = User::find($userId);

        #if(!Request::isJson() || !User::tokenCompare($token, $currentUser)) {
        if(!User::tokenCompare($token, $currentUser)) {
            if(Request::isJson()) {
                $content = json_encode(['message' => 'Você não está autenticado. Acesso negado.']);
            } else {
                $content = 'Você não está autenticado. Acesso negado.';
            }
            return Response::make($content, 403);
        }

        return $next($request);
    }
}
