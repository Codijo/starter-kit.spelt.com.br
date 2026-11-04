<?php

use Illuminate\Support\Facades\Schedule;

// Cura drift de webhook perdido (o gate é local-first). Ver spec §8.3.
Schedule::command('spelt:reconcile')->hourly();

// Consumo de crédito para o Spelt, que é o dono do saldo (CreditUsageReporter). Sem isto o lote
// vence com o `quantity_remaining` cheio e o cliente perde de novo o que já usou.
Schedule::command('spelt:credit:flush')->everyFiveMinutes()->withoutOverlapping();

// Expurgo das exportações vencidas. O prazo é por tipo (ExportCatalog); aqui só se varre.
// Diário e de madrugada: apagar arquivo é I/O, e ninguém está exportando às 3h.
Schedule::command('export:purge')->dailyAt('03:20');
