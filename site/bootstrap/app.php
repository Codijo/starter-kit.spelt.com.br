<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Rotas base; as páginas vivem em routes/web/** (auto-carregadas pelo RouteServiceProvider).
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Site público — sem middleware de auth. Adicione aqui se precisar (ex.: cache de página).
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
