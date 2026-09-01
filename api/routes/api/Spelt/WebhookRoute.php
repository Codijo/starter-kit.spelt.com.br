<?php

use App\Http\Controllers\Spelt\WebhookController;
use Illuminate\Support\Facades\Route;

// Webhook receiver — autenticado pelo Bearer secret (spelt.webhook). Ver spec §8.2.
Route::post('/spelt/webhook', WebhookController::class)->middleware('spelt.webhook');
