<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SSO de entrada do Spelt. O Customer clica "Acessar" no portal do Spelt e cai em
 * `{app_url}?spelt_token=…` (a app_url é a RAIZ do produto). O `EnsureAuthenticated`
 * intercepta o token em qualquer URL do guard e chama este exchange.
 *
 * Padrão herdado do `CheckApiToken`/`SpeltSsoService` do SMTP da iPORTO — consumir no
 * middleware (e não numa rota `/auth/spelt` dedicada) faz o SSO funcionar para QUALQUER
 * app_url configurada no Spelt, inclusive deep links.
 */
class SpeltSso
{
    /** Parâmetro que o Spelt usa ao redirecionar. NÃO é `token`. */
    public const TOKEN_PARAM = 'spelt_token';

    /**
     * Troca o spelt_token por um token de sessão do produto (via API). Retorna o token ou null.
     *
     * Usa a URL pública direto. Em dev, os containers resolvem o host público da API porque o
     * nginx do deploy o expõe como alias na rede `shared` (ver docker-compose) — o nginx roteia
     * por server_name, então nem host interno nem header Host manual são precisos.
     */
    public function exchange(string $speltToken): ?string
    {
        $url = rtrim((string) config('services.api.url'), '/').'/api/spelt/sso';

        try {
            $response = Http::acceptJson()->asJson()->timeout(15)->post($url, ['token' => $speltToken]);
        } catch (\Throwable $e) {
            Log::error('[SpeltSso] Falha de transporte no exchange do SSO', ['error' => $e->getMessage()]);

            return null;
        }

        return $response->successful() ? $response->json('data.token') : null;
    }
}
