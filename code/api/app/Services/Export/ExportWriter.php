<?php

namespace App\Services\Export;

use App\Models\Export\Export;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Escreve o arquivo, percorrendo a consulta em lotes.
 *
 * ── Por que streaming, e não `->get()` ────────────────────────────────────
 *
 * Uma exportação de leads pode ter dezenas de milhares de linhas. Carregar tudo em memória
 * para depois escrever é o caminho mais curto para um job morto por `memory_limit` — e o
 * cliente vê "Falhou" sem entender que o problema é o tamanho do pedido dele.
 *
 * `chunk()` lê por lotes e cada linha é escrita e descartada. O openspout também escreve por
 * streaming, então nem o arquivo inteiro fica na memória.
 *
 * ── Por que ';' e BOM no CSV ──────────────────────────────────────────────
 *
 * O Excel em português usa ponto-e-vírgula como separador e espera o BOM para reconhecer
 * UTF-8. Sem os dois, o cliente abre o arquivo e vê uma coluna só, com acento quebrado — e
 * conclui que a exportação está com defeito.
 */
class ExportWriter
{
    /** @return array{name:string,path:string,size:int,mime:string,checksum:string,rows:int} */
    public function write(Export $export, Exporter $exporter): array
    {
        $name = $this->fileName($export);
        // A raiz do disco já é a pasta de exportações; aqui só a conta e o arquivo.
        $relative = $export->account_id.'/'.$name;

        // O openspout escreve em caminho de sistema de arquivos, então o disco precisa ser
        // local. `Storage` cria a pasta e resolve o caminho absoluto.
        $disk = Storage::disk(ExportCatalog::DISK);
        $disk->makeDirectory(dirname($relative));
        $absolute = $disk->path($relative);

        $writer = $this->writerFor($export->format);
        $writer->openToFile($absolute);

        try {
            $writer->addRow(Row::fromValues($exporter->headings()));

            $rows = 0;
            $exporter->query()->chunk($exporter->chunkSize(), function ($batch) use ($writer, $exporter, &$rows) {
                foreach ($batch as $record) {
                    $writer->addRow(Row::fromValues($exporter->map($record)));
                    $rows++;
                }
            });
        } finally {
            // Sem o close(), o XLSX fica sem o fecho do zip e o arquivo nasce corrompido —
            // inclusive quando a consulta estoura no meio.
            $writer->close();
        }

        return [
            'name' => $name,
            'path' => $relative,
            'size' => (int) $disk->size($relative),
            'mime' => $this->mimeFor($export->format),
            'checksum' => (string) hash_file('sha256', $absolute),
            'rows' => $rows,
        ];
    }

    private function writerFor(string $format): WriterInterface
    {
        return match ($format) {
            'xlsx' => new XlsxWriter,
            default => new CsvWriter(new CsvOptions(FIELD_DELIMITER: ';', SHOULD_ADD_BOM: true)),
        };
    }

    private function mimeFor(string $format): string
    {
        return match ($format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'text/csv',
        };
    }

    /**
     * O nome que o cliente vê ao salvar: "leads-2026-09-06-1432.csv".
     *
     * Leva data e hora porque quem exporta duas vezes no mesmo dia acaba com dois arquivos na
     * pasta de downloads, e "leads (1).csv" não diz qual é qual.
     */
    private function fileName(Export $export): string
    {
        $slug = str_replace('_', '-', $export->type);

        return sprintf('%s-%s.%s', $slug, now()->format('Y-m-d-Hi'), $export->format);
    }
}
