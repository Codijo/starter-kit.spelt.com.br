<?php

namespace App\Services\Core\Account;

use App\Models\Core\Account\AccessLog;
use App\Models\Core\Account\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Grava o registro de acesso (Marco Civil, art. 15) de cada requisição autenticada. Ver AccessLog.
 *
 * Chamado no `terminate` do RecordAccessLog, depois que a resposta saiu: não atrasa o cliente.
 *
 * O IP é o `$request->ip()`: atrás do Cloudflare, o par TrustProxies + ResolveCloudflareClientIp já o
 * leva ao cliente (config/trustedproxy.php; `php artisan proxies:audit` prova a cadeia).
 *
 * À PROVA DE FALHA: nunca lança. Um erro aqui vira warning no log, e a requisição já foi respondida.
 */
class AccessLogService
{
    public function record(User $user, Request $request): void
    {
        try {
            $ip = (string) $request->ip();
            $port = $this->clientPort($request);
            $now = now();
            $date = $now->toDateString();

            // Uma gravação por usuário, IP e porta a cada `throttle_seconds`: o resto das requisições
            // desse intervalo não toca o banco.
            $key = "access-log:{$user->id}:{$ip}:{$port}:{$date}";

            if (! Cache::add($key, true, (int) config('access-log.throttle_seconds', 60))) {
                return;
            }

            AccessLog::query()->upsert([[
                'account_id' => $user->account_id,
                'user_id' => $user->id,
                'spelt_user_id' => $user->spelt_user_id,
                'email' => $user->email,
                'ip' => $ip,
                'port' => $port,
                'user_agent' => Str::limit((string) $request->userAgent(), 509) ?: null,
                'date' => $date,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]], ['user_id', 'ip', 'port', 'date'], ['last_seen_at', 'user_agent']);
        } catch (Throwable $e) {
            Log::warning('[AccessLog] Falha ao gravar o registro de acesso', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** A porta lógica de origem, do cabeçalho configurado em `access-log.port_header`; 0 = desconhecida. */
    public function clientPort(Request $request): int
    {
        $header = config('access-log.port_header');
        $port = $header ? filter_var($request->header($header), FILTER_VALIDATE_INT) : false;

        return $port !== false && $port >= 1 && $port <= 65535 ? $port : 0;
    }
}
