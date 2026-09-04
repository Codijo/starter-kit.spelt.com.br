<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida o Bearer secret dos webhooks recebidos do Spelt (`SPELT_WEBHOOK_SECRET`).
 * Alias `spelt.webhook`. Ver spec §16.
 */
class VerifySpeltWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.spelt.webhook_secret');

        if ($secret === '' || ! hash_equals($secret, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Assinatura de webhook inválida.'], 401);
        }

        return $next($request);
    }
}
