<?php

use Illuminate\Support\Facades\Route;

// EXEMPLO — o produto substitui/remove este arquivo. Rota que exige assinatura ativa
// (gate `spelt.access`). Modelo de como pendurar as rotas de valor do produto.
Route::middleware(['auth:api', 'spelt.access'])->group(function () {
    Route::get('/app/ping', fn () => response()->json(['ok' => true]));
});
