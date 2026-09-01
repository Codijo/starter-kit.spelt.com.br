<?php

namespace App\Models\Billing;

use App\Enums\Status\Billing\SubscriptionStatus;
use App\Models\Core\Account\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Subscription
 *
 * Espelho LOCAL do estado de billing da conta no Spelt. O produto NUNCA gere billing
 * (planos, cobrança, faturas vivem no Spelt) — este model apenas reflete o que os
 * webhooks do Spelt informam (`seller.subscription.*`, `access_blocked/restored`,
 * `invoice.paid`) e guarda o snapshot de entitlements do plano.
 *
 * Responsabilidades:
 * - Refletir status + período + plano da assinatura no Spelt
 * - Materializar o gate de acesso (`has_access`, respeitando inadimplência)
 * - Guardar o snapshot de entitlements do plano (lido via GET /plan/{id}) — spec §9
 *
 * Regras de negócio ficam nos Services:
 * - App\Services\Spelt\WebhookProcessor    — aplica os eventos do Spelt
 * - App\Services\Billing\EntitlementService — re-hidrata `entitlements`
 *
 * @property int $id
 * @property string $account_id
 * @property int|null $spelt_subscription_id
 * @property string|null $plan_id
 * @property string|null $plan_name
 * @property string|null $plan_slug
 * @property SubscriptionStatus $status
 * @property bool $has_access
 * @property array|null $entitlements
 * @property \Carbon\Carbon|null $current_period_end
 * @property \Carbon\Carbon|null $trial_ends_at
 * @property \Carbon\Carbon|null $activated_at
 * @property \Carbon\Carbon|null $cancelled_at
 * @property array|null $raw
 */
class Subscription extends Model
{
    protected $table = 'billing_subscriptions';

    protected $fillable = [
        // === Relacionamentos ===
        'account_id',           // FK: Account (Core\Account\Account)
        'spelt_subscription_id', // ID da Subscription no Spelt (auto-increment lá)

        // === Plano ===
        'plan_id',              // ULID do plano no Spelt
        'plan_name',
        'plan_slug',

        // === Estado / gate ===
        'status',               // SubscriptionStatus
        'has_access',           // gate — respeita bloqueio por inadimplência
        'entitlements',         // snapshot das features do plano (§9)

        // === Ciclo ===
        'current_period_end',
        'trial_ends_at',
        'activated_at',
        'cancelled_at',

        // === Auditoria ===
        'raw',                  // último payload bruto recebido do Spelt
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'has_access' => 'boolean',
            'entitlements' => 'array',
            'raw' => 'array',
            'current_period_end' => 'datetime',
            'trial_ends_at' => 'datetime',
            'activated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // === RELACIONAMENTOS ===

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    // === BUSINESS LOGIC - ESTADO (consultas simples) ===

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::ACTIVE;
    }

    /** Gate: precisa estar ativa E com acesso liberado (has_access cobre inadimplência). */
    public function hasAccess(): bool
    {
        return $this->has_access && $this->isActive();
    }
}
