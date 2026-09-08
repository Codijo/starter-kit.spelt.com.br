@props([
    // Chave do ExportCatalog na API: `lead`, `contact`, `deal`…
    'type',
    // Expressão Alpine que devolve os filtros da tela — o recorte que o arquivo terá.
    'filters' => '{}',
    // Formatos oferecidos no menu.
    'formats' => ['csv', 'xlsx'],
])

{{--
    Botão "Exportar" das listagens.

    O que sai no arquivo é o que está na TELA: os filtros vão junto no pedido. Um botão que
    ignorasse o recorte entregaria a base inteira a quem acabou de filtrar três coisas — e a
    pessoa só descobriria ao abrir a planilha.

    Não cria escopo de dado próprio além do `open` do menu: `requestExport` é global e a
    expressão de filtros é avaliada no escopo da tela.
--}}
<div x-data="{ open: false }" class="relative">
    <button type="button" @click="open = ! open" :aria-expanded="open"
        class="inline-flex items-center gap-2 rounded-md border border-border-muted bg-canvas-white px-3 py-2 text-sm font-medium text-steel-gray hover:bg-subtle-ash">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
        </svg>
        Exportar
        <svg class="h-3.5 w-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity
        @click.outside="open = false" @keydown.escape.window="open = false"
        class="absolute right-0 z-20 mt-1 w-64 rounded-lg border border-border-light bg-canvas-white p-1.5 shadow-lg">
        <p class="px-2.5 py-1.5 text-xs font-semibold uppercase tracking-wide text-steel-gray">
            Exportar o que está filtrado
        </p>

        @foreach ($formats as $format)
            <button type="button"
                @click="open = false; requestExport('{{ $type }}', '{{ $format }}', {{ $filters }})"
                class="flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-sm text-ink-black hover:bg-subtle-ash">
                <span>{{ strtoupper($format) }}</span>
                <span class="text-xs text-linear-gray-light">
                    {{ $format === 'csv' ? 'abre no Excel e no Google Sheets' : 'planilha com tipos' }}
                </span>
            </button>
        @endforeach

        <p class="mt-1 border-t border-border-light px-2.5 pt-2 text-xs text-linear-gray-light">
            O arquivo é gerado em segundo plano e fica disponível por 7 dias em Exportações.
        </p>
    </div>
</div>
