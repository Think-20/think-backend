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

        if("/".$request->route()->uri == "/briefing-files/remove/{id}" || "/".$request->route()->uri == "/briefing-files/save-multiple" || "/".$request->route()->uri == "/briefing-files/download/{id}" || "/".$request->route()->uri == "/briefing-files/download-all/{taskId}"){
            return $next($request);
        }else if("/".$request->route()->uri == "/contract-nf-files/remove/{id}" || "/".$request->route()->uri == "/contract-nf-files/save-multiple" || "/".$request->route()->uri == "/contract-nf-files/download/{id}" || "/".$request->route()->uri == "/contract-nf-files/download-all/{taskId}"){
            return $next($request);
        }else if("/".$request->route()->uri == "/project-photos-files/remove/{id}" || "/".$request->route()->uri == "/project-photos-files/save-multiple" || "/".$request->route()->uri == "/project-photos-files/download/{id}" || "/".$request->route()->uri == "/project-photos-files/download-all/{taskId}"){
            return $next($request);
        }else if(!in_array(('/' . $request->route()->uri), $urls)) {
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
