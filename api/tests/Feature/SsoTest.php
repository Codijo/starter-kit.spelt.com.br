<?php

use App\Models\Core\Account\Account;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('troca token SSO por sessão local do produto', function () {
    $tenant = (string) Str::ulid();

    Http::fake([
        '*/api/public/sso/validate*' => Http::response(['data' => [
            'tenant_id' => $tenant,
            'tenant_email' => 'joao@acme.com',
            'tenant_name' => 'Acme',
            'subscriptions' => [['status' => 'active', 'plan_name' => 'Pro', 'plan_slug' => 'pro']],
        ]]),
    ]);

    $res = $this->postJson('/api/spelt/sso', ['token' => 'sso-123'])
        ->assertStatus(201)
        ->assertJsonPath('data.account.name', 'Acme')
        ->assertJsonPath('data.has_access', true);

    expect(Account::where('spelt_tenant_id', $tenant)->exists())->toBeTrue()
        ->and($res->json('data.token'))->not->toBeEmpty();
});

it('rejeita token SSO inválido (401)', function () {
    Http::fake(['*/api/public/sso/validate*' => Http::response([], 422)]);

    $this->postJson('/api/spelt/sso', ['token' => 'ruim'])->assertStatus(401);
});
