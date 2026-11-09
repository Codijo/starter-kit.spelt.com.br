<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // IP do cliente atrás do Cloudflare + proxy do Coolify (padrão do Spelt). O `TrustProxies`
        // (stack global padrão) lê config/trustedproxy.php: o proxy do Coolify e as faixas do
        // Cloudflare, o que também mantém o X-Forwarded-Proto (https correto em URLs e redirects).
        // Este vem DEPOIS dele (append = fim do stack global, antes de todo middleware de rota,
        // `throttle` incluído) e recupera o CF-Connecting-IP só quando a cadeia termina na borda do
        // Cloudflare. `php artisan proxies:audit` confere a ordem e prova a resolução.
        $middleware->append(\App\Http\Middleware\ResolveCloudflareClientIp::class);

        // Registro de acesso (Marco Civil, art. 15) de toda requisição autenticada. Ver RecordAccessLog.
        $middleware->appendToGroup('api', \App\Http\Middleware\RecordAccessLog::class);

        // A API não tem tela de login. O padrão do Laravel manda o visitante anônimo para `route('login')`
        // ainda dentro do middleware Authenticate, quando o cliente não pede JSON: a rota não existe e o 401
        // virava 500. Sem redirect, a AuthenticationException chega ao handler, que responde JSON (abaixo).
        $middleware->redirectGuestsTo(fn () => null);

        // API-only: sem grupo web. Aliases da camada de integração com o Spelt.
        $middleware->alias([
            // Gate de acesso por assinatura (has_access + status). Ver App\Http\Middleware\EnsureSpeltAccess.
            'spelt.access'  => \App\Http\Middleware\EnsureSpeltAccess::class,
            // Valida o Bearer secret dos webhooks recebidos do Spelt.
            'spelt.webhook' => \App\Http\Middleware\VerifySpeltWebhookSecret::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Toda resposta de erro em `api/*` é JSON, peça o cliente JSON ou não: o 401 do anônimo (com o
        // redirectGuestsTo acima), o 422 de validação, que virava 302 de volta, e os demais. Visto em
        // produção e em DEV em 2026-11-07; o Platform sempre pede JSON e nunca sentiu, curl e monitor sentem.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
