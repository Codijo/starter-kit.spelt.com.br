<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de acesso por assinatura. Local-first: lê `Account::hasAccess()` (o `has_access` do
 * Spelt, sincronizado por webhook, que já inclui a tolerância do atraso). Alias `spelt.access`. Ver spec §8.3.
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
