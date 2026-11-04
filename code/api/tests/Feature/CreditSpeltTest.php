<?php

use App\Models\Billing\CreditLedger;
use App\Models\Core\Account\Account;
use App\Services\Billing\CreditService;
use App\Services\Spelt\WebhookProcessor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * O saldo é do Spelt; o produto consome no ledger e reporta (CreditUsageReporter).
 *
 * O Spelt falso guarda um lote só e o que foi consumido nele, para os testes verem o efeito
 * do consumo reportado no `quantity_remaining` que volta no vencimento.
 */
class FakeSpeltCredits
{
    public int $granted = 0;

    public int $used = 0;

    public bool $expired = false;

    /** @var list<array{quantity: int, reference: string}> */
    public array $consumes = [];

    public int $balanceCalls = 0;

    /** Respostas forçadas para o próximo consumo (ex.: [500] = a primeira chamada falha). */
    public array $consumeStatuses = [];

    public function remaining(): int
    {
        return $this->expired ? 0 : max(0, $this->granted - $this->used);
    }

    public function handle(Request $request)
    {
        if (str_ends_with($request->url(), '/balance')) {
            $this->balanceCalls++;
            $debt = max(0, $this->used - $this->granted);

            return Http::response(['data' => [
                'total_available' => $this->remaining(),
                'total_debt' => $debt,
                'net_available' => $this->remaining() - $debt,
                'batches' => [],
            ]]);
        }

        if (str_ends_with($request->url(), '/consume')) {
            $status = array_shift($this->consumeStatuses) ?? 200;

            if ($status !== 200) {
                return Http::response(['message' => 'erro'], $status);
            }

            // Replay por reference, como o Spelt: não debita de novo.
            if (! collect($this->consumes)->contains('reference', $request['reference'])) {
                $this->consumes[] = ['quantity' => $request['quantity'], 'reference' => $request['reference']];
                $this->used += $request['quantity'];
            }

            return Http::response(['data' => ['total_consumed' => $request['quantity']]]);
        }

        return Http::response(['data' => []]);
    }
}

function fakeSpelt(): FakeSpeltCredits
{
    $spelt = new FakeSpeltCredits;
    Http::fake(fn (Request $request) => $spelt->handle($request));

    return $spelt;
}

function speltAccount(): Account
{
    return Account::factory()->create(['spelt_tenant_id' => '01M1P7WFZGF0E570YSHYJ7376K']);
}

function consumeCredits(Account $account, int $count, string $prefix = 'generation'): void
{
    for ($i = 0; $i < $count; $i++) {
        app(CreditService::class)->consume($account, 1, "{$prefix}:{$i}");
    }
}

it('o saldo é o do Spelt menos o consumo que ainda não foi contado lá', function () {
    $spelt = fakeSpelt();
    $spelt->granted = 400;
    $account = speltAccount();

    consumeCredits($account, 3);

    expect($account->creditBalance())->toBe(397)
        ->and($account->creditBalance())->toBe(397)
        // A segunda leitura sai do cache.
        ->and($spelt->balanceCalls)->toBe(1);
});

it('sem resposta do Spelt o saldo cai no ledger local, e não pergunta de novo a cada leitura', function () {
    // 503 = Spelt fora; o mesmo vale para a conta do dev:token, que o Spelt não conhece (403).
    Http::fake(['*' => Http::response(['message' => 'fora'], 503)]);
    $account = speltAccount();
    app(CreditService::class)->grant($account, 50);
    consumeCredits($account, 5);

    expect($account->creditBalance())->toBe(45)
        ->and($account->creditBalance())->toBe(45);

    Http::assertSentCount(1);
});

it('o flush manda o consumo num envio só e tira as linhas da fila', function () {
    $spelt = fakeSpelt();
    $spelt->granted = 100;
    $account = speltAccount();
    consumeCredits($account, 3);

    $this->artisan('spelt:credit:flush')->assertSuccessful();

    expect($spelt->consumes)->toHaveCount(1)
        ->and($spelt->consumes[0]['quantity'])->toBe(3)
        ->and(app(CreditService::class)->pendingUsage($account))->toBe(0)
        // Depois do envio o Spelt já conta o consumo, e o pendente não desconta de novo.
        ->and($account->creditBalance())->toBe(97);

    $this->artisan('spelt:credit:flush')->assertSuccessful();

    expect($spelt->consumes)->toHaveCount(1);
});

it('o retry depois de falha reenvia o mesmo grupo com a mesma referência', function () {
    $spelt = fakeSpelt();
    $spelt->granted = 100;
    $spelt->consumeStatuses = [500];
    $account = speltAccount();
    consumeCredits($account, 2);

    $this->artisan('spelt:credit:flush')->assertFailed();

    $reference = CreditLedger::where('account_id', $account->id)->value('spelt_report_reference');

    expect($spelt->consumes)->toBeEmpty()
        ->and($reference)->not->toBeNull()
        // O que falhou ainda é crédito gasto: continua descontando.
        ->and($account->creditBalance())->toBe(98);

    // Consumo novo depois da falha vai num grupo próprio, sem mudar o que vai no retry.
    consumeCredits($account, 1, 'late');

    $this->artisan('spelt:credit:flush')->assertSuccessful();

    expect($spelt->consumes)->toHaveCount(2)
        ->and($spelt->consumes[0])->toBe(['quantity' => 2, 'reference' => $reference])
        ->and($spelt->consumes[1]['quantity'])->toBe(1)
        ->and($spelt->consumes[1]['reference'])->not->toBe($reference);
});

it('500 concedidos, 300 consumidos e o lote vence: o saldo fica em zero, não em −300', function () {
    $spelt = fakeSpelt();
    $account = speltAccount();
    $webhooks = app(WebhookProcessor::class);

    $spelt->granted = 500;
    $webhooks->handle('seller.credit.granted', ['id' => 'cred_1', 'customer_id' => $account->spelt_tenant_id, 'quantity_granted' => 500]);

    consumeCredits($account, 300);
    $this->artisan('spelt:credit:flush')->assertSuccessful();

    // O Spelt vence o lote com o que sobrou dele, já descontado o consumo reportado.
    $remaining = $spelt->remaining();
    $spelt->expired = true;
    $webhooks->handle('seller.credit.expired', ['id' => 'cred_1', 'customer_id' => $account->spelt_tenant_id, 'quantity_remaining' => $remaining]);

    expect($remaining)->toBe(200)
        ->and(app(CreditService::class)->ledgerBalance($account))->toBe(0)
        ->and($account->creditBalance())->toBe(0);

    // Retry do webhook não vence duas vezes.
    $webhooks->handle('seller.credit.expired', ['id' => 'cred_1', 'customer_id' => $account->spelt_tenant_id, 'quantity_remaining' => $remaining]);

    expect(app(CreditService::class)->ledgerBalance($account))->toBe(0);
});

it('a concessão apaga o saldo em cache', function () {
    $spelt = fakeSpelt();
    $account = speltAccount();

    expect($account->creditBalance())->toBe(0);

    $spelt->granted = 100;
    app(WebhookProcessor::class)->handle('seller.credit.granted', ['id' => 'cred_2', 'customer_id' => $account->spelt_tenant_id, 'quantity_granted' => 100]);

    expect($account->creditBalance())->toBe(100);
});

it('o dry-run não envia e aponta consumo mais antigo que um vencimento', function () {
    $spelt = fakeSpelt();
    $account = speltAccount();
    consumeCredits($account, 2);

    $this->travel(1)->minutes();
    app(CreditService::class)->expire($account, 10, 'cred_old');

    $this->artisan('spelt:credit:flush --dry-run')
        ->expectsOutputToContain('debitar o cliente duas vezes')
        ->assertSuccessful();

    expect($spelt->consumes)->toBeEmpty()
        ->and(CreditLedger::whereNotNull('spelt_report_reference')->count())->toBe(0);
});

it('o reconcile avisa quando o ledger local não fecha com o Spelt', function () {
    $spelt = fakeSpelt();
    $spelt->granted = 80;
    $account = speltAccount();
    // O ledger acha que tem 100: um vencimento ou uma revogação não chegou aqui.
    app(CreditService::class)->grant($account, 100);
    consumeCredits($account, 5);

    $this->artisan("spelt:reconcile --account={$account->id}")
        ->expectsOutputToContain('ledger local difere do Spelt em 20 crédito(s)')
        ->assertSuccessful();
});
