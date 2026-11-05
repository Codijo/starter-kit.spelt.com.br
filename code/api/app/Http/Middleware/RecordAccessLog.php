<?php

namespace App\Http\Middleware;

use App\Models\Core\Account\User;
use App\Services\Core\Account\AccessLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registro de acesso (Marco Civil, art. 15) de toda requisição autenticada da API. Fica no grupo `api`
 * (bootstrap/app.php), então rota nova com `auth:api` entra sem ninguém lembrar.
 *
 * Grava no `terminate`, depois da resposta. Só conta o usuário que o `auth:api` já resolveu: rota
 * pública (webhook, SSO) não dispara a busca do token.
 */
class RecordAccessLog
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $guard = Auth::guard('api');

        if (! $guard->hasUser() || ! ($user = $guard->user()) instanceof User) {
            return;
        }

        app(AccessLogService::class)->record($user, $request);
    }
}
