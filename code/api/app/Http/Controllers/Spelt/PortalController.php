<?php

namespace App\Http\Controllers\Spelt;

use App\Http\Controllers\Controller;
use App\Services\Spelt\SpeltClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SSO reverso (produto → portal Customer do Spelt). Gera o link de "Gerenciar
 * assinatura" para a conta autenticada. Ver spec §8.5.
 */
class PortalController extends Controller
{
    public function __construct(private readonly SpeltClient $spelt) {}

    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user()->account;

        if (! $account) {
            return $this->fail('Conta não encontrada.', 404);
        }

        // Portal Customer do Seller: APRENDIDO no SSO (`panel_url`), gravado na conta. Dispensa a
        // env SPELT_CUSTOMER_PORTAL_URL. O default só cobre a conta que ainda não fez SSO.
        // O consumo do token reverso vive na RAIZ do portal, em /sso (não em /guard nem sob
        // /@{slug}); então derivamos só scheme+host do panel_url e ignoramos o path aprendido.
        $panel = $account->spelt_panel_url ?: 'https://customer.spelt.com.br';
        $parts = parse_url($panel);
        $base = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'customer.spelt.com.br')
            .(empty($parts['port']) ? '' : ':'.$parts['port']);

        $result = $this->spelt->generateReverseSso($account->spelt_tenant_id);

        if (! $result || empty($result['token'])) {
            return $this->fail('Não foi possível gerar o link do portal.', 502);
        }

        $url = $base.'/sso?spelt_sso_token='.urlencode($result['token']);

        // Deep-link opcional: caminho DENTRO do portal Customer (ex.: /guard/me/billing/credit),
        // honrado pelo /sso do portal. Só aceita caminho relativo — nunca URL absoluta.
        $target = (string) $request->input('target', '');
        if ($target !== '' && str_starts_with($target, '/') && ! str_starts_with($target, '//')) {
            $url .= '&redirect='.urlencode($target);
        }

        return $this->ok(['url' => $url]);
    }
}
