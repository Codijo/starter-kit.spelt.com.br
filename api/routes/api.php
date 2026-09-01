<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas base (prefixo /api)
|--------------------------------------------------------------------------
| Apenas rotas globais/públicas. As rotas de domínio vivem em `routes/api/**`
| (auto-carregadas pelo RouteServiceProvider, seguindo os Models). Ver CLAUDE.md.
*/

// Liveness público.
Route::get('/ping', fn () => response()->json([
    'ok' => true,
    'product' => config('services.product.name'),
]));
