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
        
        // Permite todas as rotas de workflow
        if(strpos($routeUri, "/workflow-") === 0) {
            return $next($request);
        }

        // Liberação temporária: permitir todas as rotas de faturamento e bank-account para todos os usuários
        // (mantendo o restante das regras de permissionamento como está)
        if (
            strpos($routeUri, "/financeiro/") === 0 ||
            strpos($routeUri, "/bank-account") === 0 ||
            strpos($routeUri, "/bank-accounts") === 0
        ) {
            return $next($request);
        }

        if($routeUri == "/briefing-files/remove/{id}" || $routeUri == "/briefing-files/save-multiple" || $routeUri == "/briefing-files/download/{id}" || $routeUri == "/briefing-files/download-all/{taskId}"){
            return $next($request);
        }else if($routeUri == "/feedback/email" || $routeUri == "/feedback"){
            return $next($request);
        }else if($routeUri == "/clients/inactive" || $routeUri == "/clients/subject"){
            return $next($request);
        }else if($routeUri == "/contract-nf-files/remove/{id}" || $routeUri == "/contract-nf-files/save-multiple" || $routeUri == "/contract-nf-files/download/{id}" || $routeUri == "/contract-nf-files/download-all/{taskId}"){
            return $next($request);
        }else if($routeUri == "/project-photos-files/remove/{id}" || $routeUri == "/project-photos-files/save-multiple" || $routeUri == "/project-photos-files/download/{id}" || $routeUri == "/project-photos-files/download-all/{taskId}"){
            return $next($request);
        }else if($routeUri == "/my-clients/get/"){
            return $next($request);
        }else if(!in_array($routeUri, $urls)) {
            if($request->isJson()) {
                $content = json_encode([
                    'message' => 'Você não tem permissão para acessar essa função.'
                ]);
            } else {
                $content = 'Você não tem permissão para acessar essa função.';
            }
            return response()->make($content, 403);
        }

        return $next($request);
    }
}
