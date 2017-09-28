<?php

namespace App\Http\Middleware;

use App\User;
use Closure;

class Permission
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
        $user = User::logged();
        $funcionalities = $user->functionalities;
        $urls = array_map(function($funcionality) {
            return $funcionality['url'];
        }, $funcionalities->toArray());

        if(!in_array(('/' . $request->route()->uri), $urls)) {
            return response()->make(json_encode([
                'message' => 'Você não tem permissão para acessar essa função.'
            ]), 403);
        }

        return $next($request);
    }
}
