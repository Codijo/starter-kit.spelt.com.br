<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\TokenCookie;
use Illuminate\Http\Request;

/**
 * DEV-only. Atalho para testar o Platform sem o fluxo SSO real do Spelt: gere um token
 * na API (via tinker) e acesse `/auth/dev-login?token=<token>`. Aborta 404 em produção.
 */
class DevLoginController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(app()->isLocal(), 404);

        $token = $request->query('token');

        if (! $token) {
            return redirect()->route('landing')->withErrors(['sso' => 'Informe ?token=<token da API>.']);
        }

        return redirect()->route('dashboard')->withCookie(TokenCookie::make($token));
    }
}
