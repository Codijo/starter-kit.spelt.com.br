<?php

namespace App\Services\Billing;

use App\Enums\Type\Billing\CreditType;
use App\Models\Billing\CreditLedger;
use App\Models\Core\Account\Account;
use App\Services\Spelt\SpeltClient;
use Illuminate\Support\Facades\Cache;

/**
 * Saldo e ledger de créditos. Spec §8.6.
 *
 * ── Quem é dono do quê ────────────────────────────────────────────────────
 *
 * O SALDO é do Spelt: ele concede, expira e abate os lotes do mais antigo para o mais novo.
 * O produto consome localmente (débito por operação, ex.: uma geração) e reporta o consumo ao
 * Spelt em lote (Spelt\CreditUsageReporter, a cada 5 minutos). É o mesmo desenho da iPORTO.
 *
 * O ledger local (append-only) continua sendo escrito, por três motivos:
 *  - é a fila do consumo ainda não contado ao Spelt (`spelt_reported_at` nulo);
 *  - é a reserva quando o Spelt não responde ou a conta não tem vínculo (dev:token);
 *  - é o histórico que o dashboard usa para ritmo e autonomia.
 *
 * Até 2026-11-04 (achado na Fábrica de Lead) o consumo nunca chegava ao Spelt. O lote vencia com
 * o `quantity_remaining` cheio e o `expire()` descontava de novo o que já tinha sido gasto: 500
 * concedidos, 300 consumidos, saldo −300 no vencimento.
 */
class CreditService
{
    /**
     * Cache da leitura de saldo no Spelt.
     *
     * O produto lê o saldo antes de cada operação que consome, e o Platform a cada tela com o número. O
     * consumo local entra na hora pelo `pendingUsage()`, então o cache só atrasa o que muda
     * LÁ (concessão, vencimento, revogação), e esses eventos chegam por webhook, que apaga o
     * cache (forgetBalance).
     */
    public const BALANCE_CACHE_SECONDS = 300;

    /** Spelt fora do ar: por quanto tempo usar o ledger antes de perguntar de novo. */
    public const UNAVAILABLE_CACHE_SECONDS = 60;

    private const CACHE_PREFIX = 'spelt:credit:balance:v1:';

    public function __construct(private readonly SpeltClient $spelt) {}

    /** Concessão vinda do Spelt (`seller.credit.granted`). */
    public function grant(Account $account, int $amount, ?string $speltCreditId = null, array $meta = []): CreditLedger
    {
        return $this->entry($account, CreditType::GRANT, abs($amount), $speltCreditId, null, $meta);
    }

    /**
     * Expiração vinda do Spelt (`seller.credit.expired`).
     *
     * `$amount` é o `quantity_remaining` do lote no Spelt, que só é verdadeiro porque o consumo
     * é reportado. Consumo que chega lá depois do vencimento sai do lote seguinte, e os dois
     * lados continuam fechando igual.
     */
    public function expire(Account $account, int $amount, ?string $speltCreditId = null, array $meta = []): CreditLedger
    {
        return $this->entry($account, CreditType::EXPIRE, -abs($amount), $speltCreditId, null, $meta);
    }

    /**
     * Consumo do produto (ex.: 1 geração, N validações). `$amount` positivo — debita.
     *
     * IDEMPOTENTE por `$reference`: chamar de novo com a MESMA referência (retry de job,
     * refresh da página, reenvio de formulário) NÃO debita duas vezes — devolve a linha
     * já existente. A garantia final é o índice único (account_id, reference) no banco.
     * Contrato: passe uma referência única por operação (ex.: "generation:{id}").
     *
     * Não fala com o Spelt: a linha nasce pendente e o CreditUsageReporter a envia.
     */
    public function consume(Account $account, int $amount, string $reference, array $meta = []): CreditLedger
    {
        return $account->creditLedger()->firstOrCreate(
            ['reference' => $reference],
            [
                'type' => CreditType::CONSUME,
                'amount' => -abs($amount),
                'meta' => $meta ?: null,
            ],
        );
    }

    /**
     * Saldo de AGORA: o `net_available` do Spelt menos o consumo que ainda não foi contado lá.
     *
     * Sem subtrair o pendente, o número ficaria congelado entre um envio e outro, e o produto
     * consumiria além do saldo dentro dessa janela (foi o que a iPORTO viveu no Marketing em
     * 2026-09-14).
     *
     * Pode ser negativo: o Spelt registra como dívida o consumo além do saldo.
     *
     * Sem resposta do Spelt (fora do ar, conta sem vínculo), cai no ledger local, que espelha
     * as mesmas concessões e vencimentos. Bloquear a entrega por uma indisponibilidade nossa
     * seria pior que usar um número com alguns minutos de atraso.
     */
    public function balance(Account $account): int
    {
        $spelt = $this->speltBalance($account);

        return $spelt === null
            ? $this->ledgerBalance($account)
            : $spelt['net_available'] - $this->pendingUsage($account);
    }

    /** Saldo pelo ledger local: SUM(amount). Reserva do balance() e base da conferência. */
    public function ledgerBalance(Account $account): int
    {
        return (int) $account->creditLedger()->sum('amount');
    }

    /**
     * Consumo registrado aqui que o Spelt ainda não confirmou.
     *
     * Inclui o que já foi reivindicado por um envio que falhou: é crédito gasto de verdade, e
     * ignorá-lo faria um defeito de integração virar consumo de graça.
     */
    public function pendingUsage(Account $account): int
    {
        return (int) abs((int) $account->creditLedger()
            ->where('type', CreditType::CONSUME)
            ->whereNull('spelt_reported_at')
            ->sum('amount'));
    }

    /**
     * Saldo do tipo de crédito do produto no Spelt, com cache.
     *
     * `null` quer dizer "não deu para saber" (sem vínculo, sem tipo de crédito configurado,
     * Spelt fora do ar), nunca zero.
     *
     * @return array{net_available: int, total_available: int, total_debt: int, batches: array}|null
     */
    public function speltBalance(Account $account, bool $fresh = false): ?array
    {
        $slug = config('spelt-catalog.credit_type.slug');

        if (! $account->spelt_tenant_id || ! $slug) {
            return null;
        }

        $key = self::cacheKey($account->id);

        if (! $fresh && is_array($cached = Cache::get($key))) {
            return ($cached['unavailable'] ?? false) ? null : $cached;
        }

        $data = $this->spelt->creditBalance($account->spelt_tenant_id, $slug);

        // Resposta sem `net_available` não é saldo zero: é resposta que não entendemos.
        if (! is_array($data) || ! array_key_exists('net_available', $data)) {
            Cache::put($key, ['unavailable' => true], self::UNAVAILABLE_CACHE_SECONDS);

            return null;
        }

        $balance = [
            'net_available' => (int) $data['net_available'],
            'total_available' => (int) ($data['total_available'] ?? 0),
            'total_debt' => (int) ($data['total_debt'] ?? 0),
            'batches' => is_array($data['batches'] ?? null) ? $data['batches'] : [],
        ];

        Cache::put($key, $balance, self::BALANCE_CACHE_SECONDS);

        return $balance;
    }

    /** Descarta a leitura em cache: algo mudou no Spelt (webhook de crédito) ou o envio foi confirmado. */
    public static function forgetBalance(Account|string $account): void
    {
        Cache::forget(self::cacheKey($account instanceof Account ? $account->id : $account));
    }

    private static function cacheKey(string $accountId): string
    {
        return self::CACHE_PREFIX.$accountId;
    }

    protected function entry(Account $account, CreditType $type, int $amount, ?string $speltCreditId, ?string $reference, array $meta): CreditLedger
    {
        // Idempotente por (type, spelt_credit_id) — o lote de crédito no Spelt. Webhook duplicado
        // ou passada do reconcile não re-lançam. Sem referência do Spelt (grant local), cria direto.
        if ($speltCreditId !== null) {
            return $account->creditLedger()->firstOrCreate(
                ['type' => $type, 'spelt_credit_id' => $speltCreditId],
                ['amount' => $amount, 'reference' => $reference, 'meta' => $meta ?: null],
            );
        }

        return $account->creditLedger()->create([
            'type' => $type,
            'amount' => $amount,
            'spelt_credit_id' => $speltCreditId,
            'reference' => $reference,
            'meta' => $meta ?: null,
        ]);
    }
}
