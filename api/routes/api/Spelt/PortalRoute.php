<?php

use App\Http\Controllers\Spelt\PortalController;
use Illuminate\Support\Facades\Route;

// SSO reverso — link "Gerenciar assinatura" (produto → portal Customer do Spelt). §8.5.
Route::middleware('auth:api')->post('/spelt/portal-link', PortalController::class);
