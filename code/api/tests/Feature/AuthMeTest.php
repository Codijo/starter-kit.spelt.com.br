<?php

use App\Enums\Status\Billing\SubscriptionStatus;
use App\Models\Billing\Subscription;
use App\Models\Core\Account\Account;
use App\Models\Core\Account\User;

it('/me devolve conta, assinatura, entitlements e saldo', function () {
    $account = Account::factory()->create(['name' => 'Acme']);

    Subscription::create([
        'account_id' => $account->id,
        'status' => SubscriptionStatus::ACTIVE,
        'has_access' => true,
        'entitlements' => ['seats' => 5],
    ]);

    $user = User::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user, 'api');

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.account.name', 'Acme')
        ->assertJsonPath('data.has_access', true)
        ->assertJsonPath('data.entitlements.seats', 5)
        ->assertJsonPath('data.credit_balance', 0);
});

it('/me exige autenticação', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});
