<?php

namespace App\Console\Commands\Spelt;

use App\Services\Spelt\CreditUsageReporter;
use Illuminate\Console\Command;

/**
 * Envia ao Spelt o consumo de crédito que está no ledger e ainda não foi confirmado.
 *
 * Roda a cada 5 minutos (routes/console.php). Só a linha confirmada sai da fila; a que falhou
 * volta no próximo ciclo com a mesma `reference`, e o Spelt responde como replay se já tinha
 * debitado. Ver CreditUsageReporter.
 *
 *   php artisan spelt:credit:flush --dry-run
 *   php artisan spelt:credit:flush --account=01M1P7WFZGF0E570YSHYJ7376K
 */
class CreditFlushCommand extends Command
{
    protected $signature = 'spelt:credit:flush
        {--account= : ULID de uma conta específica}
        {--dry-run  : mostra o que seria enviado, sem enviar nem gravar}';

    protected $description = 'Envia ao Spelt o consumo de crédito pendente no ledger';

    public function handle(CreditUsageReporter $reporter): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $accounts = $reporter->accountsWithPending($this->option('account') ?: null);

        if ($accounts->isEmpty()) {
            if ($dryRun || $this->option('account')) {
                $this->info('Nenhum consumo pendente.');
            }

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->line('Banco: <comment>'.config('database.connections.mysql.database').'</comment>');

            $rows = $accounts->map(fn ($account) => [$account->id, $account->name, ...array_values($reporter->preview($account))]);
            $this->table(['Conta', 'Nome', 'Reenvio', 'Novo', 'Linhas', 'Antes do último vencimento'], $rows);

            if ($rows->sum(fn ($r) => $r[5]) > 0) {
                $this->warn('Há consumo pendente mais antigo que um vencimento: reportá-lo pode debitar o cliente duas vezes. Confira antes de enviar.');
            }

            $this->info('[DRY-RUN] Nada foi enviado.');

            return self::SUCCESS;
        }

        $sent = $failed = $quantity = 0;

        foreach ($accounts as $account) {
            foreach ($reporter->report($account) as $group) {
                $group['sent'] ? $sent++ : $failed++;
                $quantity += $group['sent'] ? $group['quantity'] : 0;
            }
        }

        $this->info("Envios ao Spelt: {$sent} ({$quantity} crédito(s)). Falhas, que voltam no próximo ciclo: {$failed}.");

        // Envio que nunca é confirmado fica parado e invisível; avisar é o que evita perder consumo em silêncio.
        if (($stuck = $reporter->stuckCount()) > 0) {
            $this->warn("{$stuck} envio(s) sem confirmação há mais de ".CreditUsageReporter::STUCK_AFTER_MINUTES.' minutos: precisam de investigação.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
