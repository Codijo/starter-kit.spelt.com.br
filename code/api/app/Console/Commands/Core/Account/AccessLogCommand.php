<?php

namespace App\Console\Commands\Core\Account;

use App\Models\Core\Account\AccessLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Consulta os registros de acesso (Marco Civil, art. 15), para atender ordem judicial. É o único caminho
 * até a tabela: nenhuma rota da API a expõe.
 *
 * Horários em UTC, como gravados.
 *
 *   php artisan account:access-log --email=fulano@empresa.com.br --from=2026-10-01 --to=2026-10-31
 *   php artisan account:access-log --ip=200.100.50.25 --from=2026-10-15
 */
class AccessLogCommand extends Command
{
    protected $signature = 'account:access-log
        {--email=   : e-mail do usuário}
        {--user=    : ULID do usuário no produto}
        {--account= : ULID da conta}
        {--ip=      : IP de origem}
        {--from=    : primeiro dia (AAAA-MM-DD, UTC)}
        {--to=      : último dia (AAAA-MM-DD, UTC)}';

    protected $description = 'Consulta os registros de acesso guardados por exigência do Marco Civil.';

    public function handle(): int
    {
        $filters = array_filter([
            'email' => $this->option('email'),
            'user_id' => $this->option('user'),
            'account_id' => $this->option('account'),
            'ip' => $this->option('ip'),
        ]);

        if ($filters === []) {
            $this->error('Informe ao menos um filtro: --email, --user, --account ou --ip.');

            return self::INVALID;
        }

        $rows = AccessLog::query()
            ->where($filters)
            ->when($this->option('from'), fn ($q, $from) => $q->where('date', '>=', Carbon::parse($from)->toDateString()))
            ->when($this->option('to'), fn ($q, $to) => $q->where('date', '<=', Carbon::parse($to)->toDateString()))
            ->orderBy('first_seen_at')
            ->get();

        $this->line('Banco: <comment>'.config('database.connections.mysql.database').'</comment> · horários em UTC');

        if ($rows->isEmpty()) {
            $this->info('Nenhum registro encontrado.');

            return self::SUCCESS;
        }

        $this->table(
            ['Dia', 'Primeiro uso', 'Último uso', 'IP', 'Porta', 'E-mail', 'Conta', 'Navegador'],
            $rows->map(fn (AccessLog $log) => [
                $log->date->toDateString(),
                $log->first_seen_at->format('H:i:s'),
                $log->last_seen_at->format('H:i:s'),
                $log->ip,
                $log->port ?: '—',
                $log->email,
                $log->account_id,
                $log->user_agent,
            ]),
        );

        $this->info("{$rows->count()} registro(s).");

        return self::SUCCESS;
    }
}
