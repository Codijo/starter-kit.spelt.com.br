<?php

use App\Models\Billing\Subscription;
use App\Models\Core\Account\Account;

it('resolve entitlements de capacidade sem tocar o banco', function () {
    $account = new Account;
    $account->setRelation('subscription', new Subscription([
        'entitlements' => ['icps_simultaneos' => 3, 'custom_domain' => true],
    ]));

    expect($account->entitlement('icps_simultaneos'))->toBe(3)
        ->and($account->allows('custom_domain'))->toBeTrue()
        ->and($account->allows('inexistente'))->toBeFalse()
        ->and($account->withinLimit('icps_simultaneos', 2))->toBeTrue()
        ->and($account->withinLimit('icps_simultaneos', 3))->toBeFalse()
        ->and($account->withinLimit('sem_limite', 999))->toBeTrue();
});

it('sem assinatura, hasAccess é falso', function () {
    $account = new Account;
    $account->setRelation('subscription', null);

    expect($account->hasAccess())->toBeFalse();
});
