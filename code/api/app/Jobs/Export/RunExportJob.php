<?php

namespace App\Jobs\Export;

use App\Models\Export\Export;
use App\Services\Export\ExportCatalog;
use App\Services\Export\Exporter;
use App\Services\Export\ExportWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Gera o arquivo de uma exportação.
 *
 * Um job só para todos os tipos: o que muda entre eles é o `Exporter`, resolvido pelo
 * catálogo. Um job por tipo seria a mesma máquina copiada N vezes, e o próximo produto do kit
 * copiaria de novo.
 *
 * ── Por que poucas tentativas ─────────────────────────────────────────────
 *
 * Exportação que falha por dado (coluna nula onde não devia, filtro inválido) vai falhar
 * igual nas próximas — repetir 25 vezes só ocupa fila e adia o "Falhou" que o cliente
 * precisa ver. Duas tentativas cobrem o caso real de retentativa: um soluço de banco.
 */
class RunExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public readonly string $exportId) {}

    public function handle(ExportWriter $writer): void
    {
        $export = Export::find($this->exportId);

        if (! $export) {
            // A linha some se a conta for removida no meio do caminho. Não é erro do job.
            Log::info('[Export] Pedido não existe mais.', ['export_id' => $this->exportId]);

            return;
        }

        $class = ExportCatalog::exporterFor($export->type);

        if (! $class) {
            $export->markFailed('Tipo de exportação desconhecido: '.$export->type);

            return;
        }

        $export->markProcessing();

        try {
            /** @var Exporter $exporter */
            $exporter = new $class($export);

            $file = $writer->write($export, $exporter);
            $export->markCompleted($file);

            Log::info('[Export] Arquivo pronto.', [
                'export_id' => $export->id,
                'type' => $export->type,
                'format' => $export->format,
                'rows' => $file['rows'],
                'size_kb' => round($file['size'] / 1024, 1),
            ]);
        } catch (\Throwable $e) {
            // A mensagem vai para a tela do cliente: curta, sem arquivo nem linha.
            $export->markFailed($e->getMessage());

            Log::error('[Export] Falhou.', [
                'export_id' => $export->id,
                'type' => $export->type,
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Esgotadas as tentativas, a linha não pode ficar em "Gerando" para sempre.
     *
     * O `handle` já marca a falha, mas há como o job morrer sem passar por ele — timeout e
     * `MaxAttemptsExceeded` matam o processo por fora.
     */
    public function failed(\Throwable $exception): void
    {
        $export = Export::find($this->exportId);

        if ($export && $export->status->isOpen()) {
            $export->markFailed($exception->getMessage());
        }
    }
}
