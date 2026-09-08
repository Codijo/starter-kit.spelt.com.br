<?php

use App\Http\Controllers\Export\ExportController;
use Illuminate\Support\Facades\Route;

/**
 * Exportações — uma tela só. Não há `create`: o pedido nasce do botão "Exportar" da
 * listagem, com o recorte que estava na tela.
 */
Route::middleware('auth.token')->group(function () {
    Route::get('/exports', [ExportController::class, 'index'])->name('export.index');
});
