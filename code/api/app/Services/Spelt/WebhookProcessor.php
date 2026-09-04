<?php

namespace App\Services\Spelt;

use App\Enums\Status\Billing\SubscriptionStatus;
use App\Enums\Status\Core\AccountStatus;
use App\Models\Core\Account\Account;
use App\Services\Billing\CreditService;
use App\Services\Core\Account\AccountService;

/**
 * Roteia um evento de webhook do Spelt para a ação de domínio. Roda dentro do
 * ProcessWebhookJob (já assíncrono) — provisioner é chamado direto; a implementação
 * do produto enfileira trabalho pesado se precisar. Spec §8.2.
 *
 * Todos os eventos `seller.subscription.*` refletem o estado AUTORITATIVO (status +
 * has_access do DTO) via [[SubscriptionSync]] — a mesma peça usada pelo SSO e pelo
 * reconcile. Assim webhook perdido/fora de ordem é curado pela conciliação sem divergir.
 */
class WebhookProcessor
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly CreditService $credits,
        private readonly \App\Contracts\ProductProvisioner $provisioner,
        private readonly SubscriptionSync $subscriptions,
    ) {}

    public function handle(string $event, array $data): void
    {
        match ($event) {
            'seller.customer.created',
            'seller.customer.activated'          => $this->customerUpserted($data),
            'seller.customer.suspended'          => $this->accountStatus($data, AccountStatus::SUSPENDED),
            'seller.customer.cancelled'          => $this->customerCancelled($data),

            // Estado da assinatura sempre pelo SubscriptionSync (status + has_access autoritativos).
            'seller.subscription.created',
            'seller.subscription.activated',
            'seller.subscription.updated',
            'seller.subscription.upgraded',
            'seller.subscription.downgraded',
            'seller.subscription.cancellation_scheduled',
            'seller.subscription.cancelled',
            'seller.subscription.renewed',
            'seller.subscription.expired',
            'seller.subscription.trial_ended',
            'seller.subscription.payment_failed',
            'seller.subscription.access_blocked',
            'seller.subscription.access_restored' => $this->syncSubscription($data),

            'seller.invoice.paid'                => $this->invoicePaid($data),

            'seller.credit.granted'              => $this->creditGranted($data),
            'seller.credit.expired'              => $this->creditExpired($data),

            default => null, // eventos não relevantes ao produto são ignorados
        };
    }

    // === customer ===

    private function customerUpserted(array $data): void
    {
        $account = $this->accounts->upsertFromWebhook($data);
        $this->provisioner->provision($account);
    }

    private function accountStatus(array $data, AccountStatus $status): void
    {
        $this->account($data)?->update(['status' => $status]);
    }

    private function customerCancelled(array $data): void
    {
        $account = $this->account($data);
        if (! $account) {
            return;
        }
        $account->update(['status' => AccountStatus::CANCELLED]);
        $this->provisioner->teardown($account);
    }

    // === subscription ===

    /** Reflete o estado autoritativo da assinatura no mirror (cria a conta se necessário). */
    private function syncSubscription(array $data): void
    {
        $this->subscriptions->apply($this->accountOrCreate($data), $data);
    }

    /**
     * Fatura paga: o payload é de FATURA (sem os campos da assinatura), então só destrava a
     * assinatura existente. O `subscription.renewed`/`activated` que acompanha o pagamento é
     * quem carrega o estado completo (via syncSubscription).
     */
    private function invoicePaid(array $data): void
    {
        $sub = $this->account($data)?->subscription;
        if ($sub && ! $sub->has_access) {
            $sub->status = SubscriptionStatus::ACTIVE;
            $sub->has_access = true;
            $sub->activated_at ??= now();
            $sub->save();
        }
    }

    // === credit ===

    private function creditGranted(array $data): void
    {
        // accountOrCreate (não account): a entrega do `credit.granted` costuma CHEGAR ANTES do
        // `customer.created` (ordem não garantida). Com account() a concessão era pulada e o
        // event-id ficava PROCESSED, então nem o reenvio recuperava. Criando a conta placeholder,
        // o crédito sempre pousa; o nome real é enriquecido quando o customer/SSO chega.
        $this->credits->grant($this->accountOrCreate($data), $this->creditAmount($data, 'quantity_granted'), $this->creditRef($data), ['event' => 'granted']);
    }

    private function creditExpired(array $data): void
    {
        $this->credits->expire($this->accountOrCreate($data), $this->creditAmount($data, 'quantity_remaining'), $this->creditRef($data), ['event' => 'expired']);
    }

    // === helpers ===

    private function account(array $data): ?Account
    {
        $id = $this->customerId($data);

        return $id ? Account::where('spelt_tenant_id', $id)->first() : null;
    }

    private function accountOrCreate(array $data): Account
    {
        return $this->accounts->ensure(
            $this->customerId($data),
            $data['customer_name'] ?? $data['name'] ?? $data['customer_email'] ?? $data['email'] ?? null,
        );
    }

    /**
     * ID do customer no payload. O CustomerDTO expõe o customer como `id`; os demais DTOs
     * (subscription/invoice/credit) trazem `customer_id` (e um `id` próprio) — por isso a ordem.
     */
    private function customerId(array $data): ?string
    {
        return $data['customer_id'] ?? $data['id'] ?? null;
    }

    /** Quantidade do crédito. CreditDTO usa quantity_granted/remaining; aceita `amount` legado. */
    private function creditAmount(array $data, string $preferred): int
    {
        return (int) ($data[$preferred] ?? $data['amount'] ?? 0);
    }

    /** Referência do lote de crédito (idempotência do ledger). CreditDTO expõe como `id`. */
    private function creditRef(array $data): ?string
    {
        $ref = $data['id'] ?? $data['credit_id'] ?? null;

        return $ref !== null ? (string) $ref : null;
    }
}
