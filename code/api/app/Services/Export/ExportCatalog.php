<?php

namespace App\Services\Export;

/**
 * O que este produto sabe exportar.
 *
 * Nasce vazio: o kit traz a máquina, o produto traz os tipos.
 *
 * ── Como acrescentar um tipo ──────────────────────────────────────────────
 *
 * 1. Escreva a classe estendendo `Exporter` — três respostas: quais colunas (`headings`),
 *    qual consulta (`query`) e como uma linha vira linha de planilha (`map`).
 * 2. Acrescente a entrada em `all()`:
 *
 *        'lead' => [
 *            'label' => 'Leads',
 *            'description' => 'A fila de triagem, com pontuação e situação.',
 *            'exporter' => LeadExporter::class,
 *            'formats' => self::FORMATS,
 *            'ttl_days' => 7,
 *        ],
 *
 * Não há passo 3: fila, arquivo, prazo, expurgo, download e tela já funcionam. Na tela, o
 * botão é `<x-shared.export-button type="lead" filters="exportFilters()" />`.
 *
 * ── Por que é uma allowlist ───────────────────────────────────────────────
 *
 * O tipo vem da tela e vira nome de classe a instanciar. Aceitar qualquer string é deixar o
 * cliente escolher o que roda no servidor.
 *
 * ── Por que o TTL é por tipo ──────────────────────────────────────────────
 *
 * Exportação de gente é uma cópia de dado pessoal parada em disco; exportação de
 * configuração, não. Prazo curto é higiene para a primeira e incômodo para a segunda — então
 * quem decide é o tipo, uma linha por vez.
 */
class ExportCatalog
{
    /**
     * Onde os arquivos moram.
     *
     * Disco próprio, e não o `local`, por causa das permissões: o worker da fila escreve e o
     * PHP-FPM lê, e são usuários diferentes. Ver a nota em `config/filesystems.php`.
     *
     * Não há rota servindo esta pasta — o download passa pelo ExportController, que confere
     * a conta antes de entregar o arquivo.
     */
    public const DISK = 'exports';

    public const FORMATS = ['csv', 'xlsx'];

    private const DEFAULT_TTL_DAYS = 7;

    /**
     * Substitui o catálogo durante um teste.
     *
     * Existe para a suíte poder exercitar a máquina — arquivo, prazo, expurgo — sem depender
     * dos tipos que o produto ainda vai escrever. Mesma ideia de `Http::fake()`. Fora de
     * teste, ninguém chama.
     *
     * @var array<string,array<string,mixed>>|null
     */
    private static ?array $fake = null;

    public static function fake(array $types): void
    {
        self::$fake = $types;
    }

    public static function restore(): void
    {
        self::$fake = null;
    }

    /**
     * `exporter`  classe que estende Exporter
     * `formats`   formatos oferecidos para este tipo
     * `ttl_days`  dias até o expurgo apagar o arquivo
     *
     * @return array<string, array<string,mixed>>
     */
    public static function all(): array
    {
        return self::$fake ?? [
            // Os tipos do seu produto entram aqui. Ver o exemplo no topo desta classe.
        ];
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    public static function has(string $type): bool
    {
        return self::get($type) !== null;
    }

    public static function label(string $type): string
    {
        return self::get($type)['label'] ?? $type;
    }

    public static function ttlDays(string $type): int
    {
        return (int) (self::get($type)['ttl_days'] ?? self::DEFAULT_TTL_DAYS);
    }

    public static function supportsFormat(string $type, string $format): bool
    {
        return in_array($format, self::get($type)['formats'] ?? [], true);
    }

    public static function exporterFor(string $type): ?string
    {
        return self::get($type)['exporter'] ?? null;
    }

    /** O catálogo no formato que a tela consome. */
    public static function forUi(): array
    {
        return collect(self::all())->map(fn (array $meta, string $key) => [
            'value' => $key,
            'label' => $meta['label'],
            'description' => $meta['description'] ?? null,
            'formats' => $meta['formats'],
            'ttl_days' => $meta['ttl_days'] ?? self::DEFAULT_TTL_DAYS,
        ])->values()->all();
    }
}
