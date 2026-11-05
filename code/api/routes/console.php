<?php

use App\Models\Core\Account\AccessLog;
use App\Models\Spelt\WebhookEvent;
use Illuminate\Support\Facades\Schedule;

// Cura drift de webhook perdido (o gate é local-first). Ver spec §8.3.
Schedule::command('spelt:reconcile')->hourly();

// Consumo de crédito para o Spelt, que é o dono do saldo (CreditUsageReporter). Sem isto o lote
// vence com o `quantity_remaining` cheio e o cliente perde de novo o que já usou.
Schedule::command('spelt:credit:flush')->everyFiveMinutes()->withoutOverlapping();

// Expurgo das exportações vencidas. O prazo é por tipo (ExportCatalog); aqui só se varre.
// Diário e de madrugada: apagar arquivo é I/O, e ninguém está exportando às 3h.
Schedule::command('export:purge')->dailyAt('03:20');

// Históricos vencidos: webhooks recebidos do Spelt (WebhookEvent::prunable()), em 90 dias; registros
// de acesso (AccessLog::prunable()), em 7 meses. Os models vão explícitos: sem `--model`, o comando
// varre app/Models atrás de qualquer Prunable, e um model novo entraria na limpeza sem ninguém decidir.
Schedule::command('model:prune', ['--model' => [WebhookEvent::class, AccessLog::class]])->dailyAt('03:40');

// Drift das faixas do Cloudflare (config/trustedproxy.php). Faixa nova publicada lá fora não dá erro:
// quem entrar por ela passa a ser registrado com o IP da borda. O `--upstream` compara com a lista
// publicada e sai com 1 quando divergir; o `onFailure` vira exceção reportada, senão ficaria só no log.
Schedule::command('proxies:audit --upstream')
    ->weeklyOn(1, '04:00')
    ->environments(['production'])
    ->onFailure(fn () => report(new RuntimeException('proxies:audit --upstream falhou: faixas do Cloudflare ou cadeia de proxies divergiram (config/trustedproxy.php).')));
