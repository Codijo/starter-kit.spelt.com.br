<?php

namespace App\Http\Middleware;

use App\Support\SpeltSso;
use App\Support\TokenCookie;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porta de entrada dos painéis. Faz duas coisas:
 *
 *  1. Consome o SSO do Spelt: quando a URL traz `?spelt_token=…`, troca o token por uma sessão
 *     e redireciona pra mesma URL sem o parâmetro. Interceptar aqui — e não numa rota
 *     `/auth/spelt` dedicada — faz o SSO funcionar para QUALQUER `app_url` configurada no Spelt
 *     (que é a RAIZ do produto), inclusive deep links.
 *  2. Barra quem não tem sessão, mandando pro login (que orienta a entrar pelo painel do Spelt).
 *
 * Só checa se o cookie EXISTE (não valida) — a validade é resolvida server-side pela API
 * (401 → interceptor do axios manda pro /logout).
 */
class EnsureAuthenticated
{
    public function __construct(private readonly SpeltSso $sso) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($token = $request->query(SpeltSso::TOKEN_PARAM)) {
            return $this->consumeSso($request, (string) $token);
        }

        if (! $request->cookie(TokenCookie::NAME)) {
            return redirect()->route('landing');
        }

        return $next($request);
    }

    /**
     * Troca o token do Spelt por sessão e redireciona limpando a URL. O token vale ~60s e é de
     * uso único (a validação o destrói) — sem limpar a URL, um F5 tentaria reconsumi-lo e cairia
     * em "link expirado" mesmo já autenticado.
     */
    private function consumeSso(Request $request, string $speltToken): Response
    {
        $apiToken = $this->sso->exchange($speltToken);

        if (! $apiToken) {
            // Sem sessão prévia → login; com sessão, segue com ela e só limpa a URL.
            if (! $request->cookie(TokenCookie::NAME)) {
                return redirect()->route('landing')->withErrors(['sso' => 'Link de acesso inválido ou expirado.']);
            }

            return redirect($this->urlWithoutToken($request));
        }

        return redirect($this->urlWithoutToken($request))->withCookie(TokenCookie::make($apiToken));
    }

    /** Mesma URL, sem o parâmetro do token. */
    private function urlWithoutToken(Request $request): string
    {
        $query = $request->query();
        unset($query[SpeltSso::TOKEN_PARAM]);

        return $request->url().($query ? '?'.http_build_query($query) : '');
    }
}
