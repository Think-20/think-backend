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
        $userId = (int) $request->header('User');
        $token = $request->header('Authorization');

        if(empty($userId) || empty($token)) {
            $content = json_encode(['message' => 'Por favor, forneça sua chave de autenticação.']);
            return Response::make($content, 403);
        }

        $currentUser = User::find($userId);

        if(!Request::isJson() || !User::tokenCompare($token, $currentUser)) {
            $content = json_encode(['message' => 'Você não está autenticado. Acesso negado.']);
            return Response::make($content, 403);
        }

        return $next($request);
    }
}
