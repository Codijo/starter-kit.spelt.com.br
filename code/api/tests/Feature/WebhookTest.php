<?php

use App\Enums\Status\Billing\SubscriptionStatus;
use App\Models\Core\Account\Account;
use App\Models\Spelt\WebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::fake([
        '*/api/seller/external/v1/plan/*' => Http::response(['data' => ['features' => ['seats' => 5]]]),
    ]);
});

it('rejeita webhook sem o secret correto', function () {
    test()->withHeaders(['Authorization' => 'Bearer errado'])
        ->postJson('/api/spelt/webhook', ['id' => 'e1', 'event' => 'seller.invoice.paid', 'data' => []])
        ->assertStatus(401);
});

it('subscription.activated cria conta, ativa e hidrata entitlements', function () {
    $tenant = (string) Str::ulid();

    // Shape real do webhook = SubscriptionDTO da External v1 (id, status, has_access).
    postWebhook([
        'id' => 'evt_1',
        'event' => 'seller.subscription.activated',
        'data' => [
            'customer_id' => $tenant,
            'id' => 42,
            'plan_id' => (string) Str::ulid(),
            'status' => 'active',
            'has_access' => true,
        ],
    ])->assertOk();

    $account = Account::where('spelt_tenant_id', $tenant)->first();

    expect($account)->not->toBeNull()
        ->and($account->subscription->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($account->subscription->spelt_subscription_id)->toBe(42)
        ->and($account->hasAccess())->toBeTrue()
        ->and($account->subscription->entitlements)->toBe(['seats' => 5]);
});

it('trial conta como ativo, com acesso (subscription.created status=trial)', function () {
    $tenant = (string) Str::ulid();

    postWebhook([
        'id' => 'evt_trial',
        'event' => 'seller.subscription.created',
        'data' => [
            'customer_id' => $tenant,
            'id' => 43,
            'plan_id' => (string) Str::ulid(),
            'status' => 'trial',
            'has_access' => true,
            'trial_end' => now()->addDays(7)->toIso8601String(),
        ],
    ])->assertOk();

    $account = Account::where('spelt_tenant_id', $tenant)->first();

    expect($account->subscription->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($account->hasAccess())->toBeTrue()
        ->and($account->subscription->trial_ends_at)->not->toBeNull();
});

it('ignora webhook duplicado (mesmo id)', function () {
    $payload = [
        'id' => 'evt_dup',
        'event' => 'seller.invoice.paid',
        'data' => ['customer_id' => (string) Str::ulid()],
    ];

    postWebhook($payload)->assertOk();
    postWebhook($payload)->assertOk();

    expect(WebhookEvent::where('spelt_event_id', 'evt_dup')->count())->toBe(1);
});

it('customer.created cria a conta a partir do CustomerDTO (id/name/email)', function () {
    $tenant = (string) Str::ulid();

    // Shape real do CustomerDTO: o customer é `id` (não `customer_id`), com `name`/`email`.
    postWebhook([
        'id' => 'evt_cust',
        'event' => 'seller.customer.created',
        'data' => ['id' => $tenant, 'name' => 'ACME', 'email' => 'dono@acme.test'],
    ])->assertOk();

    $account = Account::where('spelt_tenant_id', $tenant)->first();
    expect($account)->not->toBeNull()
        ->and($account->name)->toBe('ACME')
        ->and($account->users()->where('email', 'dono@acme.test')->exists())->toBeTrue();
});

it('credit.granted credita o saldo (CreditDTO: customer_id + quantity_granted)', function () {
    $tenant = (string) Str::ulid();
    Account::factory()->create(['spelt_tenant_id' => $tenant]);

    // Shape real do CreditDTO: quantity_granted (não `amount`), id do lote (não `credit_id`).
    postWebhook([
        'id' => 'evt_credit',
        'event' => 'seller.credit.granted',
        'data' => ['customer_id' => $tenant, 'id' => 77, 'quantity_granted' => 120],
    ])->assertOk();

    expect(Account::where('spelt_tenant_id', $tenant)->first()->creditBalance())->toBe(120);
});

it('credit.granted cria a conta se chegar ANTES do customer.created (ordem não garantida)', function () {
    $tenant = (string) Str::ulid();

    // Sem conta prévia — o Spelt entrega o crédito antes do customer.created.
    postWebhook([
        'id' => 'evt_credit_race',
        'event' => 'seller.credit.granted',
        'data' => ['customer_id' => $tenant, 'id' => 88, 'quantity_granted' => 30],
    ])->assertOk();

    $account = Account::where('spelt_tenant_id', $tenant)->first();
    expect($account)->not->toBeNull()
        ->and($account->name)->toBe('Conta')   // placeholder — sem nome no CreditDTO
        ->and($account->creditBalance())->toBe(30);

    // customer.created chega depois e enriquece o nome placeholder.
    postWebhook([
        'id' => 'evt_credit_race_cust',
        'event' => 'seller.customer.created',
        'data' => ['id' => $tenant, 'name' => 'ACME Tarde', 'email' => 'tarde@acme.test'],
    ])->assertOk();

    expect($account->fresh()->name)->toBe('ACME Tarde')
        ->and(Account::where('spelt_tenant_id', $tenant)->first()->creditBalance())->toBe(30);
});
