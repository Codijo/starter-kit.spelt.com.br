<?php

use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\SsoController;
use Illuminate\Support\Facades\Route;

// Handoff do portal Customer do Spelt: /auth/spelt?spelt_token=...
Route::get('/auth/spelt', SsoController::class)->middleware('throttle:sso')->name('sso');

Route::get('/logout', LogoutController::class)->name('logout');

// DEV-only: testar sem o SSO real (aborta 404 em produção).
Route::get('/auth/dev-login', DevLoginController::class)->name('dev-login');
