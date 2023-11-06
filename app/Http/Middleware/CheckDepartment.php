<?php

namespace App\Http\Middleware;

use App\User;
use Closure;
use Exception;

class CheckDepartment
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
        $user = User::logged(); // Supondo que você tenha um método chamado "logged" para obter o usuário logado.

        if ($user && $user->employee->department_id !== 1) {
            // O usuário não tem permissão para acessar este grupo de rotas.
            // throw new Exception('Acesso negado. permissão apenas para Diretoria.');
            return response()->json(['Erro' => 'Acesso negado. permissão apenas para Diretoria.'], 403);
        }

        return $next($request);
    }
}
