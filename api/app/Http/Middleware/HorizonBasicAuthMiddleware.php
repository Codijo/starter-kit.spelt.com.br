<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Gate do dashboard do Horizon via HTTP Basic Auth (padrão herdado do Spelt).
 *
 * A API é stateless (Sanctum por token) — não há sessão de "operador" para um gate por e-mail
 * como o `viewHorizon` padrão. Basic Auth resolve isso de forma uniforme em local E produção
 * (o gate padrão libera o dashboard em `local`, o que deixava a rota aberta em dev).
 *
 * Credenciais em `config('horizon.basic_auth.*')` (env `HORIZON_BASIC_AUTH_USERNAME/PASSWORD`).
 * Sem credenciais configuradas → ninguém entra (401), fail-safe.
 */
class HorizonBasicAuthMiddleware
{
    public function handle($request, Closure $next)
    {
        $user = config('horizon.basic_auth.username');
        $pass = config('horizon.basic_auth.password');

        // Fail-safe: sem credenciais configuradas, o dashboard fica trancado.
        if (empty($user) || empty($pass)
            || $request->getUser() !== $user
            || $request->getPassword() !== $pass) {
            return response()->make('Invalid credentials.', 401, ['WWW-Authenticate' => 'Basic']);
        }

        return $next($request);
    }
}
