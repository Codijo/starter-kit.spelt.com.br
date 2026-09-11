<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Atrás de um proxy reverso que termina o TLS na borda: confiar nele para
        // ler X-Forwarded-Proto e gerar URLs/assets em https. Sem isto o @vite emite
        // assets http → Mixed Content bloqueado. O container só é alcançável pelo
        // proxy, então `*` é seguro.
        $middleware->trustProxies(at: '*');

        // Guard de sessão: exige o cookie de token (o produto entrou via SSO). Ver
        // App\Http\Middleware\EnsureAuthenticated (espelha o CheckApiToken do Customer).
        $middleware->alias([
            'auth.token' => \App\Http\Middleware\EnsureAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
