<?php

use App\Models\Core\Account\Account;
use App\Models\Core\Account\User;
use Illuminate\Support\Facades\Http;

it('gera o link do portal com o panel_url aprendido no SSO', function () {
    Http::fake([
        '*/customer/*/auth-token' => Http::response(['data' => [
            'token' => 'splt_sso_abc',
            'expires_at' => now()->addMinute()->toIso8601String(),
        ]]),
    ]);

    $account = Account::factory()->create(['spelt_panel_url' => 'https://acme.customer.spelt.test/@acme/guard']);
    $user = User::factory()->create(['account_id' => $account->id]);

    // Deriva scheme+host do panel_url e aponta pro /sso na raiz (ignora slug/guard).
    $this->actingAs($user, 'api')->postJson('/api/spelt/portal-link')
        ->assertOk()
        ->assertJsonPath('data.url', 'https://acme.customer.spelt.test/sso?spelt_sso_token=splt_sso_abc');
});

it('cai no portal default quando a conta ainda não aprendeu o panel_url', function () {
    Http::fake([
        '*/customer/*/auth-token' => Http::response(['data' => [
            'token' => 'splt_sso_xyz',
            'expires_at' => now()->addMinute()->toIso8601String(),
        ]]),
    ]);

    $account = Account::factory()->create(['spelt_panel_url' => null]);
    $user = User::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user, 'api')->postJson('/api/spelt/portal-link')
        ->assertOk()
        ->assertJsonPath('data.url', 'https://customer.spelt.com.br/sso?spelt_sso_token=splt_sso_xyz');
});

it('exige autenticação', function () {
    $this->postJson('/api/spelt/portal-link')->assertUnauthorized();
});
