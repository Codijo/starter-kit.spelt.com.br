<?php

namespace App\Services\Billing;

use App\Enums\Type\Billing\CreditType;
use App\Models\Billing\CreditLedger;
use App\Models\Core\Account\Account;

/**
 * Gestão do saldo de créditos (ledger append-only). O Spelt CONCEDE (grant/expire via
 * webhook); o PRODUTO consome (débito arbitrário por operação). Saldo = SUM(amount).
 * Spec §8.6.
 */
class CreditService
{
    /** Concessão vinda do Spelt (`seller.credit.granted`). */
    public function grant(Account $account, int $amount, ?string $speltCreditId = null, array $meta = []): CreditLedger
    {
        return $this->entry($account, CreditType::GRANT, abs($amount), $speltCreditId, null, $meta);
    }

    /** Expiração vinda do Spelt (`seller.credit.expired`). */
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

    public function balance(Account $account): int
    {
        return (int) $account->creditLedger()->sum('amount');
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
