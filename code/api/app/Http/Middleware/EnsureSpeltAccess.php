<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de acesso por assinatura. Local-first: lê `Account::hasAccess()` (status ativo
 * + has_access sincronizados por webhook). Alias `spelt.access`. Ver spec §8.3.
 */
class EnsureSpeltAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user()?->account;

        if (! $account || ! $account->hasAccess()) {
            return response()->json(['message' => 'Assinatura ativa necessária.'], 403);
        }

        return $next($request);
    }
}
