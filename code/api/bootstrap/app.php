<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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

        // API-only: sem grupo web. Aliases da camada de integração com o Spelt.
        $middleware->alias([
            // Gate de acesso por assinatura (has_access + status). Ver App\Http\Middleware\EnsureSpeltAccess.
            'spelt.access'  => \App\Http\Middleware\EnsureSpeltAccess::class,
            // Valida o Bearer secret dos webhooks recebidos do Spelt.
            'spelt.webhook' => \App\Http\Middleware\VerifySpeltWebhookSecret::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
