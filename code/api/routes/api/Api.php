<?php

use Illuminate\Support\Facades\Route;

// Fallback 404 para rotas /api desconhecidas (convenção do Spelt).
Route::fallback(fn () => response()->json(['error' => 'Recurso não encontrado'], 404));
