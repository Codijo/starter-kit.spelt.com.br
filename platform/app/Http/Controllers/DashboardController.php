<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /** Painel — carrega o contexto (/me) via axios no front. */
    public function index(): View
    {
        return view('web.dashboard');
    }

    /** Tela de bloqueio quando a assinatura não dá acesso (gate). */
    public function billingRequired(): View
    {
        return view('web.billing_required');
    }
}
