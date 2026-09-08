<?php

use App\Http\Controllers\Export\ExportController;
use Illuminate\Support\Facades\Route;

$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware(['auth:api', 'spelt.access'])
    ->prefix('export')
    ->group(function () use ($ulid) {
        Route::get('/exports', [ExportController::class, 'index']);
        Route::post('/exports', [ExportController::class, 'store']);
        Route::get('/exports/{export}', [ExportController::class, 'show'])->where('export', $ulid);

        // Sem `update`: exportação é um fato consumado. Refazer é pedir outra.
        Route::get('/exports/{export}/download', [ExportController::class, 'download'])->where('export', $ulid);
        Route::delete('/exports/{export}', [ExportController::class, 'destroy'])->where('export', $ulid);
    });
