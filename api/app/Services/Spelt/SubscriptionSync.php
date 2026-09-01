<?php

namespace App\Services\Spelt;

use App\Contracts\ProductProvisioner;
use App\Enums\Status\Billing\SubscriptionStatus;
use App\Models\Billing\Subscription;
use App\Models\Core\Account\Account;
use App\Services\Billing\EntitlementService;

/**
 * Fonte ÚNICA de verdade para refletir uma assinatura do Spelt no mirror local.
 *
 * Usado por SSO (baseline no login), Webhook (tempo real) e Reconcile (rede de segurança).
 * Todos passam por aqui para que o mapeamento seja idêntico — em especial:
 *
 *  - **Trial** conta como ATIVO. O mirror não tem status `trial` e o gate é `has_access &&
 *    status===ACTIVE` ([[Subscription::hasAccess]]); o trial tem acesso, então mapeia p/ ACTIVE.
 *    O front distingue trial por `trial_ends_at`.
 *  - **`has_access` autoritativo**: quando o Spelt informa (DTO/webhook), usa direto — cobre
 *    trial e carência de inadimplência. Sem ele (payload de SSO), deriva de active/trial.
 *  - **Leitura flexível de campos**: o SSO manda `id`/`current_period_ends_at`; o DTO da External
 *    v1 manda `id`/`current_period_end`/`trial_end`/`has_access`; o webhook mistura. Lê os dois.
 *  - **Mapa de status**: os valores do Spelt (`canceled`, `expired`, `paused`, `pending_payment`)
 *    não batem com o enum do produto — traduz explicitamente.
 */
class SubscriptionSync
{
    public function __construct(
        private readonly EntitlementService $entitlements,
        private readonly ProductProvisioner $provisioner,
    ) {}

    /** Aplica os dados de uma assinatura do Spelt no mirror. Idempotente. */
    public function apply(Account $account, array $data): Subscription
    {
        $sub = Subscription::firstOrNew(['account_id' => $account->id]);
        $old = $sub->entitlements ?? [];

        if (($id = $data['id'] ?? $data['subscription_id'] ?? null) !== null) {
            $sub->spelt_subscription_id = $id;
        }
        foreach (['plan_id', 'plan_name', 'plan_slug'] as $field) {
            if (($data[$field] ?? null) !== null) {
                $sub->{$field} = $data[$field];
            }
        }
        if (($period = $data['current_period_end'] ?? $data['current_period_ends_at'] ?? null) !== null) {
            $sub->current_period_end = $period;
        }
        if (($trialEnd = $data['trial_end'] ?? $data['trial_ends_at'] ?? null) !== null) {
            $sub->trial_ends_at = $trialEnd;
        }

        $rawStatus = $data['status'] ?? null;
        $sub->status = $this->mapStatus($rawStatus, $sub->status);

        $sub->has_access = array_key_exists('has_access', $data)
            ? (bool) $data['has_access']
            : in_array($rawStatus, ['active', 'trial'], true);

        if ($sub->has_access) {
            $sub->activated_at ??= now();
        }

        $sub->raw = $data;
        $sub->save();

        $new = $this->entitlements->refresh($sub);
        if ($old != $new) {
            $this->provisioner->onPlanChanged($account, $old, $new);
        }

        return $sub;
    }

    /** Traduz o status do Spelt para o enum do produto. `null` = não veio → mantém o atual (ou INACTIVE se novo). */
    private function mapStatus(?string $spelt, ?SubscriptionStatus $current): SubscriptionStatus
    {
        return match ($spelt) {
            null => $current ?? SubscriptionStatus::INACTIVE,
            'active', 'trial' => SubscriptionStatus::ACTIVE,   // trial = ativo (gate é has_access)
            'past_due' => SubscriptionStatus::PAST_DUE,
            'canceled', 'cancelled' => SubscriptionStatus::CANCELLED,
            default => SubscriptionStatus::INACTIVE,           // expired, paused, pending_payment, inactive, desconhecido
        };
    }
}
