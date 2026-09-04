<?php

use App\Models\Billing\Subscription;
use App\Models\Core\Account\Account;
use App\Models\Core\Account\User;

it('nega acesso sem assinatura ativa (403)', function () {
    $account = Account::factory()->create();
    Subscription::create(['account_id' => $account->id, 'status' => 'past_due', 'has_access' => false]);
    $user = User::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user, 'api')->getJson('/api/app/ping')->assertStatus(403);
});

it('libera acesso com assinatura ativa (200)', function () {
    $account = Account::factory()->create();
    Subscription::create(['account_id' => $account->id, 'status' => 'active', 'has_access' => true]);
    $user = User::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user, 'api')->getJson('/api/app/ping')->assertOk();
});
