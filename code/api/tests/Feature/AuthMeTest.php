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

it('sem token e sem pedir JSON, a resposta ainda é 401 em JSON, não um redirect para um login que não existe', function () {
    // Regressão de 2026-11-07: curl e monitores sem `Accept: application/json` recebiam 500, porque o
    // Laravel tentava redirecionar o visitante anônimo para a rota `login`. Ver bootstrap/app.php.
    $this->get('/api/me')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('validação em api/* responde 422 em JSON mesmo sem pedir JSON', function () {
    // Sem o shouldRenderJsonWhen, a ValidationException de um POST sem `Accept: application/json`
    // virava 302 de volta à página anterior. A exportação é a única rota do kit com `validate()`.
    $account = Account::factory()->create();
    Subscription::create(['account_id' => $account->id, 'status' => SubscriptionStatus::ACTIVE, 'has_access' => true]);
    $this->actingAs(User::factory()->create(['account_id' => $account->id]), 'api');

    $this->post('/api/export/exports', [])
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/json')
        ->assertJsonValidationErrors(['type', 'format']);
});
