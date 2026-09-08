<?php

namespace App\Console\Commands\Export;

use App\Models\Export\Export;
use App\Services\Export\ExportCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Apaga as exportações vencidas — a linha e o arquivo.
 *
 * ── Por que isto existe ───────────────────────────────────────────────────
 *
 * Um arquivo de exportação de leads é uma cópia de dado pessoal de terceiros parada em disco,
 * fora de qualquer controle de acesso do produto. Sem expurgo, ela fica lá para sempre: a
 * conta cancela a assinatura e o CSV com dez mil CNPJs continua no servidor.
 *
 * O prazo é por tipo, declarado no `ExportCatalog` — ver `expires_at`, gravado no pedido e
 * renovado quando o arquivo fica pronto.
 *
 * ── Por que também varre arquivo órfão ────────────────────────────────────
 *
 * Linha apagada à mão, banco restaurado de um backup antigo, job que morreu entre escrever o
 * arquivo e gravar o caminho: em todos, o arquivo fica no disco sem ninguém apontando para
 * ele, e nenhuma consulta o encontraria nunca mais.
 */
class PurgeExportsCommand extends Command
{
    protected $signature = 'export:purge
        {--dry-run : Só mostra o que seria apagado}';

    protected $description = 'Apaga exportações vencidas (linha + arquivo) e arquivos órfãos.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $expired = Export::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $bytes = 0;

        foreach ($expired as $export) {
            $bytes += (int) $export->file_size;

            $this->line(sprintf(
                '  %s %s · %s · %s · venceu %s',
                $dryRun ? '·' : '-',
                $export->id,
                ExportCatalog::label($export->type),
                $export->format,
                $export->expires_at->diffForHumans(),
            ));

            if (! $dryRun) {
                $export->deleteFile();
                $export->delete();
            }
        }

        $orphans = $this->orphanFiles($dryRun);

        $this->newLine();
        $this->info(sprintf(
            '%s %d exportação(ões) vencida(s) (%.1f MB) e %d arquivo(s) órfão(s).',
            $dryRun ? 'Seriam apagadas:' : 'Apagadas:',
            $expired->count(),
            $bytes / 1048576,
            $orphans,
        ));

        return self::SUCCESS;
    }

    /** Arquivos no disco que nenhuma linha reivindica. */
    private function orphanFiles(bool $dryRun): int
    {
        $disk = Storage::disk(ExportCatalog::DISK);

        $known = Export::whereNotNull('file_path')->pluck('file_path')->flip();
        $count = 0;

        foreach ($disk->allFiles() as $path) {
            if ($known->has($path)) {
                continue;
            }

            $this->line(($dryRun ? '  · ' : '  - ').$path.' (órfão)');
            $count++;

            if (! $dryRun) {
                $disk->delete($path);
            }
        }

        return $count;
    }
}
