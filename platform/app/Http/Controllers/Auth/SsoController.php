<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SpeltSso;
use App\Support\TokenCookie;
use Illuminate\Http\Request;

/**
 * Handoff do portal Customer do Spelt via rota dedicada (`/auth/spelt?spelt_token=...`).
 *
 * Redundante com o `EnsureAuthenticated` (que consome o token em QUALQUER URL do guard, que é
 * como o Spelt manda hoje — a app_url é a raiz). Mantido como fallback para app_urls que apontem
 * explicitamente para cá. Reusa o `SpeltSso` para não duplicar a troca (e herdar o host interno).
 */
class SsoController extends Controller
{
    public function __construct(private readonly SpeltSso $sso) {}

    public function __invoke(Request $request)
    {
        $token = $request->query(SpeltSso::TOKEN_PARAM);

        if (! $token) {
            return redirect()->route('landing')->withErrors(['sso' => 'Token SSO ausente.']);
        }

        $apiToken = $this->sso->exchange((string) $token);

        if (! $apiToken) {
            return redirect()->route('landing')
                ->withErrors(['sso' => 'Não foi possível autenticar via Spelt. Token inválido ou expirado.']);
        }

        return redirect()->route('dashboard')->withCookie(TokenCookie::make($apiToken));
    }
}
