<?php

namespace App\Console\Commands\Spelt;

use App\Enums\Status\Billing\SubscriptionStatus;
use App\Models\Core\Account\Account;
use App\Services\Billing\CreditService;
use App\Services\Spelt\SpeltClient;
use App\Services\Spelt\SubscriptionSync;
use Illuminate\Console\Command;

/**
 * Reconcilia o mirror de assinatura/entitlements com o Spelt — rede de segurança do gate
 * (local-first). O webhook é o caminho rápido, mas é best-effort: pode se perder, o endpoint
 * pode ficar desabilitado após falhas seguidas, ou o evento pode nem existir (ex.: o trial não
 * emite `subscription.activated`). Sem esta rotina, qualquer uma dessas situações vira acesso
 * concedido (ou negado) indevidamente por tempo indeterminado.
 *
 * Roda sobre a FONTE DA VERDADE: `GET /customer/{id}/subscription` (status + has_access
 * autoritativos), aplicada via [[SubscriptionSync]] — o mesmo mapeamento do SSO e do webhook.
 */
class ReconcileCommand extends Command
{
    protected $signature = 'spelt:reconcile
        {--account=       : ULID de uma conta específica}
        {--stale-hours=24 : reconcilia quem não sincroniza há N horas (ignorado com --all/--account)}
        {--all            : reconcilia todas as contas, sem filtro de staleness}
        {--limit=500      : máximo de contas por execução}';

    protected $description = 'Reconcilia assinatura/entitlements com o Spelt (rede de segurança p/ webhook perdido).';

    public function handle(SpeltClient $spelt, SubscriptionSync $sync, CreditService $credits): int
    {
        $query = Account::query()->whereNotNull('spelt_tenant_id');

        if ($id = $this->option('account')) {
            $query->whereKey($id);
        } elseif (! $this->option('all')) {
            // Sem assinatura (nunca sincronizou) OU com o mirror velho (sem webhook recente).
            $stale = now()->subHours(max(1, (int) $this->option('stale-hours')));
            $query->where(fn ($q) => $q
                ->whereDoesntHave('subscription')
                ->orWhereHas('subscription', fn ($s) => $s->where('updated_at', '<', $stale)));
        }

        $accounts = $query->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();

        if ($accounts->isEmpty()) {
            $this->info('Nada a reconciliar.');

            return self::SUCCESS;
        }

        $this->info("Reconciliando {$accounts->count()} conta(s)...");
        $synced = $errored = 0;

        foreach ($accounts as $account) {
            try {
                $subs = $spelt->getCustomerSubscriptions($account->spelt_tenant_id) ?? [];

                $chosen = collect($subs)
                    ->first(fn ($s) => in_array($s['status'] ?? null, ['active', 'trial'], true))
                    ?? ($subs[0] ?? null);

                if ($chosen) {
                    $sync->apply($account, $chosen);
                } elseif ($sub = $account->subscription) {
                    // Nenhuma assinatura viva no Spelt (cancelou e perdemos o webhook) → trava o acesso.
                    $sub->status = SubscriptionStatus::INACTIVE;
                    $sub->has_access = false;
                    $sub->save();
                }

                // Créditos: espelha os lotes ATIVOS do Spelt (idempotente por spelt_credit_id) —
                // cura `credit.granted` perdido/malformado. O consumo local não é tocado.
                foreach ($spelt->getCustomerCredits($account->spelt_tenant_id) ?? [] as $lot) {
                    if (($qty = (int) ($lot['quantity_granted'] ?? 0)) > 0 && ! empty($lot['id'])) {
                        $credits->grant($account, $qty, (string) $lot['id'], ['event' => 'reconcile']);
                    }
                }

                $synced++;
            } catch (\Throwable $e) {
                $errored++;
                $this->warn("  ✗ account={$account->id}: {$e->getMessage()}");
            }
        }

        $this->info("Reconciliadas: {$synced}. Erros: {$errored}.");

        return $errored > 0 ? self::FAILURE : self::SUCCESS;
    }
}
