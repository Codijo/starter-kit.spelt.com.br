<?php

use App\Enums\Type\Billing\CreditType;
use App\Models\Billing\CreditLedger;
use App\Models\Core\Account\Account;
use App\Services\Billing\CreditService;

it('saldo de créditos = soma do ledger (grant − consume − expire)', function () {
    $account = Account::factory()->create();

    CreditLedger::create(['account_id' => $account->id, 'type' => CreditType::GRANT, 'amount' => 100]);
    CreditLedger::create(['account_id' => $account->id, 'type' => CreditType::CONSUME, 'amount' => -30]);
    CreditLedger::create(['account_id' => $account->id, 'type' => CreditType::EXPIRE, 'amount' => -10]);

    expect($account->creditBalance())->toBe(60);
});

it('consume é idempotente por reference — retry não debita duas vezes', function () {
    $account = Account::factory()->create();
    $service = app(CreditService::class);
    $service->grant($account, 100);

    $first = $service->consume($account, 1, 'generation:abc');
    $second = $service->consume($account, 1, 'generation:abc'); // mesma referência (retry)

    expect($second->id)->toBe($first->id);              // devolveu a mesma linha
    expect($account->creditBalance())->toBe(99);         // debitou UMA vez
    expect(CreditLedger::where('reference', 'generation:abc')->count())->toBe(1);
});

it('consume com referências distintas debita cada uma', function () {
    $account = Account::factory()->create();
    $service = app(CreditService::class);
    $service->grant($account, 100);

    $service->consume($account, 1, 'generation:aaa');
    $service->consume($account, 1, 'generation:bbb');

    expect($account->creditBalance())->toBe(98);
});
