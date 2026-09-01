<?php

use App\Http\Controllers\Spelt\SsoController;
use Illuminate\Support\Facades\Route;

// Troca de token SSO (o Platform chama). Ver spec §8.1.
Route::post('/spelt/sso', SsoController::class)->middleware('throttle:sso');
