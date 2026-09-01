<?php

use App\Contracts\ProductProvisioner;
use App\Models\Billing\Subscription;
use App\Models\Core\Account\Account;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::fake([
        '*/api/seller/external/v1/plan/*' => Http::response(['data' => ['features' => ['seats' => 3]]]),
    ]);
});

it('customer.created dispara provision()', function () {
    $spy = Mockery::mock(ProductProvisioner::class);
    $spy->shouldReceive('provision')->once();
    $this->app->instance(ProductProvisioner::class, $spy);

    postWebhook([
        'id' => 'prov_1',
        'event' => 'seller.customer.created',
        'data' => ['customer_id' => (string) Str::ulid(), 'customer_email' => 'a@b.com', 'customer_name' => 'A'],
    ])->assertOk();
});

it('subscription.upgraded dispara onPlanChanged()', function () {
    $tenant = (string) Str::ulid();
    $account = Account::factory()->create(['spelt_tenant_id' => $tenant]);
    Subscription::create([
        'account_id' => $account->id,
        'status' => 'active',
        'has_access' => true,
        'plan_id' => (string) Str::ulid(),
        'entitlements' => ['seats' => 1],
    ]);

    $spy = Mockery::mock(ProductProvisioner::class);
    $spy->shouldReceive('onPlanChanged')->once();
    $this->app->instance(ProductProvisioner::class, $spy);

    postWebhook([
        'id' => 'prov_2',
        'event' => 'seller.subscription.upgraded',
        'data' => ['customer_id' => $tenant, 'plan_id' => (string) Str::ulid(), 'plan_name' => 'Pro'],
    ])->assertOk();
});

it('customer.cancelled dispara teardown()', function () {
    $tenant = (string) Str::ulid();
    Account::factory()->create(['spelt_tenant_id' => $tenant]);

    $spy = Mockery::mock(ProductProvisioner::class);
    $spy->shouldReceive('teardown')->once();
    $this->app->instance(ProductProvisioner::class, $spy);

    postWebhook([
        'id' => 'prov_3',
        'event' => 'seller.customer.cancelled',
        'data' => ['customer_id' => $tenant],
    ])->assertOk();
});
