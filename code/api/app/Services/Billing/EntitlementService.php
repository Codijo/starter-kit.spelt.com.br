<?php

namespace App\Services\Billing;

use App\Models\Billing\Subscription;
use App\Services\Spelt\SpeltClient;

/**
 * Re-hidrata o snapshot de entitlements (features do plano) a partir do Spelt.
 * Fonte da verdade dos limites é o Spelt (PlanFeature/PlanMetric). Spec §9.
 */
class EntitlementService
{
    public function __construct(private readonly SpeltClient $spelt) {}

    /** Busca o plano no Spelt e persiste o snapshot em `subscription.entitlements`. */
    public function refresh(Subscription $subscription): array
    {
        if (! $subscription->plan_id) {
            return $subscription->entitlements ?? [];
        }

        $plan = $this->spelt->getPlan($subscription->plan_id);
        $entitlements = $plan ? $this->extract($plan) : ($subscription->entitlements ?? []);

        // O SubscriptionDTO do webhook traz `plan_id`, mas NÃO traz nome nem slug do plano.
        // Como já buscamos o plano inteiro aqui para extrair as features, aproveitamos a mesma
        // resposta para preencher a identificação — sem isso a tela mostra a assinatura sem plano.
        $fill = ['entitlements' => $entitlements];
        if ($plan) {
            $fill['plan_name'] = $plan['name'] ?? $subscription->plan_name;
            $fill['plan_slug'] = $plan['slug'] ?? $subscription->plan_slug;
        }

        $subscription->forceFill($fill)->save();

        return $entitlements;
    }

    /**
     * Normaliza as features do plano num mapa chave→valor. Best-effort: cobre as
     * formas mais comuns do payload do Spelt. O produto pode sobrescrever este método
     * para o seu shape específico de features.
     */
    public function extract(array $plan): array
    {
        $features = $plan['features'] ?? [];

        // Já é um mapa chave→valor (ex.: {"seats": 5, "custom_domain": true}).
        if (is_array($features) && $features !== [] && ! array_is_list($features)) {
            return $features;
        }

        // Lista de objetos [{slug|key, value|default_value}].
        $out = [];
        foreach ($features as $feature) {
            $key = $feature['slug'] ?? $feature['key'] ?? null;
            if ($key === null) {
                continue;
            }
            $out[$key] = $feature['value'] ?? $feature['default_value'] ?? true;
        }

        return $out;
    }
}
