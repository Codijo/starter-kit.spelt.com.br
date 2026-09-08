<?php

namespace App\Services\Export;

use App\Enums\Status\Export\ExportStatus;
use App\Jobs\Export\RunExportJob;
use App\Models\Export\Export;

/**
 * Abre um pedido de exportação.
 *
 * ── Por que aqui, e não no `created` do model ─────────────────────────────
 *
 * Um produto vizinho despacha o job de dentro do `boot()` do model. Funciona, mas quem lê o
 * controller não vê que ali começa trabalho de fila — e um seed ou um teste que crie a linha
 * dispara exportação sem querer. Aqui a intenção está escrita.
 */
class ExportService
{
    /**
     * @param  array<string,mixed>  $filters  o recorte da tela, guardado como veio
     *
     * @throws ExportException quando o tipo, o formato ou o momento não permitem
     */
    public function request(string $accountId, ?string $userId, string $type, string $format, array $filters = []): Export
    {
        if (! ExportCatalog::has($type)) {
            throw new ExportException('Esse tipo de exportação não existe.');
        }

        if (! ExportCatalog::supportsFormat($type, $format)) {
            throw new ExportException('Esse formato não está disponível para este tipo.');
        }

        $this->guardAgainstDuplicate($accountId, $type);

        $export = Export::create([
            'account_id' => $accountId,
            'user_id' => $userId,
            'type' => $type,
            'format' => $format,
            'status' => ExportStatus::PENDING,
            'filters' => $filters,
            // Já nasce com prazo. A conclusão renova, mas a exportação que FALHAR nunca
            // chegaria lá — e ficaria no histórico para sempre.
            'expires_at' => now()->addDays(ExportCatalog::ttlDays($type)),
        ]);

        RunExportJob::dispatch($export->id);

        return $export;
    }

    /**
     * Um pedido em aberto por tipo, por conta.
     *
     * Exportar é caro: varre a base inteira do cliente. Sem esta guarda, quem clica três
     * vezes porque "não aconteceu nada" dispara três varreduras — e as três competem pela
     * mesma fila, deixando a primeira ainda mais lenta.
     */
    private function guardAgainstDuplicate(string $accountId, string $type): void
    {
        $open = Export::where('account_id', $accountId)
            ->where('type', $type)
            ->whereIn('status', [ExportStatus::PENDING->value, ExportStatus::PROCESSING->value])
            ->exists();

        if ($open) {
            throw new ExportException(
                'Já existe uma exportação de '.mb_strtolower(ExportCatalog::label($type))
                .' em andamento. Espere terminar para pedir outra.'
            );
        }
    }
}
