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

        $routeUri = '/' . ltrim((string) $request->route()->uri(), '/');

        // Permite todas as rotas de workflow
        if (strpos($routeUri, '/workflow-') === 0) {
            return $next($request);
        }

        // Permite todas as rotas de fund e funds (ex.: POST /funds/all, GET /funds/get/{id})
        if (self::isFundRoute($routeUri)) {
            return $next($request);
        }

        if (strpos($routeUri, '/cedente') !== false) {
            return $next($request);
        }

        if ($routeUri == '/briefing-files/remove/{id}' || $routeUri == '/briefing-files/save-multiple' || $routeUri == '/briefing-files/download/{id}' || $routeUri == '/briefing-files/download-all/{taskId}') {
            return $next($request);
        } elseif ($routeUri == '/feedback/email' || $routeUri == '/feedback') {
            return $next($request);
        } elseif ($routeUri == '/clients/inactive' || $routeUri == '/clients/subject') {
            return $next($request);
        } elseif ($routeUri == '/contract-nf-files/remove/{id}' || $routeUri == '/contract-nf-files/save-multiple' || $routeUri == '/contract-nf-files/download/{id}' || $routeUri == '/contract-nf-files/download-all/{taskId}') {
            return $next($request);
        } elseif ($routeUri == '/project-photos-files/remove/{id}' || $routeUri == '/project-photos-files/save-multiple' || $routeUri == '/project-photos-files/download/{id}' || $routeUri == '/project-photos-files/download-all/{taskId}') {
            return $next($request);
        } elseif ($routeUri == '/my-clients/get/{id}') {
            return $next($request);
        } elseif (! in_array($routeUri, $urls)) {
            if ($request->isJson()) {
                $content = json_encode([
                    'message' => 'Você não tem permissão para acessar essa função.',
                ]);

                return response()->make($content, 403, ['Content-Type' => 'application/json']);
            }

            $content = 'Você não tem permissão para acessar essa função.';

            return response()->make($content, 403);
        }

        return $next($request);
    }

    /**
     * Rotas /fund/* e /funds/* liberadas para qualquer usuário autenticado.
     *
     * @param string $routeUri URI do padrão da rota (ex.: /funds/all)
     * @return bool
     */
    private static function isFundRoute($routeUri)
    {
        return (bool) preg_match('#^/(fund|funds)(/|$)#', $routeUri);
    }
}
