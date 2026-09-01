<?php

use App\Http\Controllers\Core\Account\MeController;
use Illuminate\Support\Facades\Route;

// Contexto do usuário autenticado.
Route::middleware('auth:api')->group(function () {
    Route::get('/me', MeController::class);
});
