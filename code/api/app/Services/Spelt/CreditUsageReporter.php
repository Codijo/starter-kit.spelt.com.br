<?php

namespace App\Services\Spelt;

use App\Enums\Type\Billing\CreditType;
use App\Models\Billing\CreditLedger;
use App\Models\Core\Account\Account;
use App\Services\Billing\CreditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Conta ao Spelt o consumo de crédito registrado no ledger (`POST …/credit/{slug}/consume`).
 *
 * Mesmo papel do buffer da iPORTO (SpeltCreditService + spelt:credit:flush), com o ledger
 * fazendo de fila: a linha de consumo nasce com `spelt_reported_at` nulo e só sai da fila
 * quando o Spelt confirma.
 *
 * ── Por que reivindicar antes de enviar ───────────────────────────────────
 *
 * O envio leva várias linhas numa chamada só, com uma `reference`. Se a chamada der timeout,
 * não dá para saber se o Spelt debitou. O retry precisa mandar a MESMA referência e a MESMA
 * quantidade, para o Spelt responder como replay. Por isso a referência é gravada nas
 * linhas ANTES do envio: o retry reenvia exatamente aquele grupo, e o consumo que chegar
 * depois entra num grupo novo. Uma referência por janela de tempo (iPORTO) perderia a linha
 * cuja transação commita depois de a janela já ter sido enviada.
 */
class CreditUsageReporter
{
    /** Envio reivindicado e não confirmado há mais que isto é tratado como travado. */
    public const STUCK_AFTER_MINUTES = 60;

    public function __construct(private readonly SpeltClient $spelt) {}

    /** Contas com vínculo no Spelt e consumo ainda não confirmado. */
    public function accountsWithPending(?string $accountId = null): Collection
    {
        return Account::query()
            ->whereNotNull('spelt_tenant_id')
            ->when($accountId, fn ($q) => $q->whereKey($accountId))
            ->whereHas('creditLedger', fn (Builder $q) => $this->pending($q))
            ->orderBy('id')
            ->get();
    }

    /**
     * Reivindica o consumo novo e envia todos os grupos pendentes da conta.
     *
     * @return list<array{reference: string, quantity: int, sent: bool}>
     */
    public function report(Account $account): array
    {
        $slug = config('spelt-catalog.credit_type.slug');

        if (! $account->spelt_tenant_id || ! $slug) {
            return [];
        }

        // UPDATE atômico: duas execuções simultâneas não reivindicam a mesma linha.
        $this->pending($account->creditLedger()->getQuery())
            ->whereNull('spelt_report_reference')
            ->update(['spelt_report_reference' => 'usage:'.Str::lower((string) Str::ulid())]);

        $results = [];

        foreach ($this->claimedGroups($account) as $group) {
            $sent = $group['quantity'] <= 0 || $this->spelt->consumeCredit(
                $account->spelt_tenant_id,
                $slug,
                $group['quantity'],
                $group['reference'],
                config('app.name').": {$group['rows']} consumo(s)",
            ) !== null;

            if ($sent) {
                $account->creditLedger()
                    ->where('spelt_report_reference', $group['reference'])
                    ->update(['spelt_reported_at' => now()]);
            }

            $results[] = ['reference' => $group['reference'], 'quantity' => $group['quantity'], 'sent' => $sent];
        }

        if (collect($results)->contains('sent', true)) {
            CreditService::forgetBalance($account);
        }

        return $results;
    }

    /**
     * O que um envio agora levaria, sem gravar nada.
     *
     * `before_expiry` conta as linhas pendentes mais antigas que o último vencimento da conta.
     * Antes desta integração, o vencimento descontou o lote cheio, inclusive o que essas linhas
     * consumiram. Reportá-las agora debitaria o cliente de novo no Spelt; é o mesmo corte do
     * `spelt:credit:repair-buffer` da iPORTO.
     *
     * @return array{retry: int, new: int, rows: int, before_expiry: int}
     */
    public function preview(Account $account): array
    {
        $pending = $this->pending($account->creditLedger()->getQuery());

        $lastExpiry = $account->creditLedger()->where('type', CreditType::EXPIRE)->max('created_at');

        return [
            'retry' => (int) abs((int) (clone $pending)->whereNotNull('spelt_report_reference')->sum('amount')),
            'new' => (int) abs((int) (clone $pending)->whereNull('spelt_report_reference')->sum('amount')),
            'rows' => (clone $pending)->count(),
            'before_expiry' => $lastExpiry ? (clone $pending)->where('created_at', '<', $lastExpiry)->count() : 0,
        ];
    }

    /** Envios reivindicados que o Spelt não confirma há mais de STUCK_AFTER_MINUTES. */
    public function stuckCount(): int
    {
        return CreditLedger::query()
            ->where('type', CreditType::CONSUME)
            ->whereNotNull('spelt_report_reference')
            ->whereNull('spelt_reported_at')
            ->where('updated_at', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES))
            ->distinct()
            ->count('spelt_report_reference');
    }

    /** @return list<array{reference: string, quantity: int, rows: int}> */
    private function claimedGroups(Account $account): array
    {
        return $this->pending($account->creditLedger()->getQuery())
            ->whereNotNull('spelt_report_reference')
            ->selectRaw('spelt_report_reference, -SUM(amount) as quantity, COUNT(*) as rows_count, MIN(id) as first_id')
            ->groupBy('spelt_report_reference')
            ->orderBy('first_id')
            ->get()
            ->map(fn ($g) => [
                'reference' => (string) $g->spelt_report_reference,
                'quantity' => (int) $g->quantity,
                'rows' => (int) $g->rows_count,
            ])
            ->all();
    }

    private function pending(Builder $query): Builder
    {
        return $query->where('type', CreditType::CONSUME->value)->whereNull('spelt_reported_at');
    }
}
