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
        // Atrás de um proxy reverso que termina o TLS na borda: confiar nele para
        // ler X-Forwarded-Proto — scheme https correto em URLs, cookies secure e
        // redirects. O container só é alcançável pelo proxy, então `*` é seguro.
        $middleware->trustProxies(at: '*');

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
