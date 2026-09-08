<?php

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Exportações — o histórico dos arquivos pedidos.
 *
 * Casca apenas. O download não passa por aqui: o arquivo mora na API, em disco privado, e é
 * ela que confere a conta antes de entregar.
 */
class ExportController extends Controller
{
    public function index(): View
    {
        return view('web.export.index');
    }
}
