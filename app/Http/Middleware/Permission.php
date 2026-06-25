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

        $routeUri = "/" . $request->route()->uri;

        if (self::isPermissionBypassed($routeUri)) {
            return $next($request);
        }

        if (! in_array($routeUri, $urls)) {
            if ($request->isJson()) {
                $content = json_encode([
                    'message' => 'Você não tem permissão para acessar essa função.',
                ]);
            } else {
                $content = 'Você não tem permissão para acessar essa função.';
            }

            return response()->make($content, 403);
        }

        return $next($request);
    }

    /**
     * Rotas liberadas sem checagem de funcionalidade cadastrada no usuario.
     *
     * @param string $routeUri
     * @return bool
     */
    private static function isPermissionBypassed($routeUri)
    {
        $prefixes = [
            '/workflow-',
            '/financeiro/',
            '/financeiro-files/',
            '/bank-account',
            '/bank-accounts',
            '/category/',
            '/categories',
            '/tag/',
            '/tags',
            '/cedente',
            '/fund',
            '/jobs/get/',
            '/tasks/get/',
        ];

        foreach ($prefixes as $prefix) {
            if (strpos($routeUri, $prefix) === 0) {
                return true;
            }
        }

        $exactRoutes = [
            '/feedback/email',
            '/feedback',
            '/clients/inactive',
            '/clients/subject',
            '/my-clients/get/',
            '/briefing-files/remove/{id}',
            '/briefing-files/save-multiple',
            '/briefing-files/download/{id}',
            '/briefing-files/download-all/{taskId}',
            '/contract-nf-files/remove/{id}',
            '/contract-nf-files/save-multiple',
            '/contract-nf-files/download/{id}',
            '/contract-nf-files/download-all/{taskId}',
            '/project-photos-files/remove/{id}',
            '/project-photos-files/save-multiple',
            '/project-photos-files/download/{id}',
            '/project-photos-files/download-all/{taskId}',
        ];

        return in_array($routeUri, $exactRoutes, true);
    }
}
