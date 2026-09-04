<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Página de contato + recebimento do formulário. A ENTREGA da mensagem (e-mail/CRM) é um
 * gancho do produto — por padrão só registra no log. Ver o TODO em store().
 */
class ContactController extends Controller
{
    public function show(): View
    {
        return view('web.contact');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        // TODO (produto): entregar de verdade — Mail::to(config('site.contact_email'))->send(...),
        // ou POST a um CRM/webhook. Por enquanto, só registra no log.
        Log::info('[site] Novo contato', $data);

        return back()->with('success', 'Recebemos sua mensagem! Em breve entramos em contato.');
    }
}
