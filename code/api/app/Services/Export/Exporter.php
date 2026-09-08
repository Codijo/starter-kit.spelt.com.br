<?php

namespace App\Services\Export;

use App\Models\Export\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * O que um produto escreve para poder exportar alguma coisa.
 *
 * Três respostas: quais colunas, qual consulta e como vira linha. O resto — formato, arquivo,
 * fila, prazo, download — é do kit.
 *
 * ⚠️ `query()` roda dentro de um job, sem requisição e sem usuário autenticado. Não use
 * `forCurrentAccount()` aqui: o isolamento por conta vem de `$this->accountId()`, que sai da
 * própria linha do Export. Esquecer isso exporta a base inteira para um cliente só.
 *
 * A consulta é percorrida por `chunk()`, então precisa ter ordenação estável — sem `orderBy`,
 * o MySQL pode repetir ou pular linhas entre os lotes.
 */
abstract class Exporter
{
    public function __construct(protected readonly Export $export) {}

    /** Cabeçalho, na ordem das colunas. */
    abstract public function headings(): array;

    /** A consulta a percorrer, isolada por conta e recortada pelos filtros. */
    abstract public function query(): Builder;

    /** Uma linha do arquivo, na mesma ordem de `headings()`. */
    abstract public function map(Model $row): array;

    /**
     * Quantas linhas por lote.
     *
     * Quinhentas é o meio-termo medido em produtos parecidos: lotes pequenos multiplicam
     * consultas, grandes seguram muito objeto Eloquent na memória ao mesmo tempo.
     */
    public function chunkSize(): int
    {
        return 500;
    }

    protected function accountId(): string
    {
        return $this->export->account_id;
    }

    /** @return array<string,mixed> */
    protected function filters(): array
    {
        return $this->export->filters ?? [];
    }

    protected function filter(string $key): mixed
    {
        return $this->filters()[$key] ?? null;
    }

    protected function hasFilter(string $key): bool
    {
        $value = $this->filter($key);

        return $value !== null && $value !== '';
    }

    /** Data no formato que o Excel brasileiro entende sem reinterpretar. */
    protected function date(mixed $value): string
    {
        return $value?->format('d/m/Y H:i') ?? '';
    }

    /** Booleano legível: "Sim"/"Não" cabe na planilha melhor que 1/0. */
    protected function yesNo(?bool $value): string
    {
        return $value ? 'Sim' : 'Não';
    }
}
