{{--
  Breadcrumb (padrão App). Cada item aceita:
    - estático:  ['label' => 'Marcas', 'url' => route('studio.brands')]
    - dinâmico:  ['xLabel' => "item.brand?.name || 'Marca'", 'xUrl' => "'/brands/' + item.brand_id"]
      (xLabel/xUrl são EXPRESSÕES Alpine — o breadcrumb deve viver dentro do x-data da tela).
  O último item é sempre o atual (sem link). Início (home) é fixo à esquerda.
--}}
@props(['items' => []])

<nav class="flex flex-wrap items-center gap-1.5 text-sm text-linear-gray-light" aria-label="Breadcrumb">
    <a href="{{ route('dashboard') }}" class="inline-flex items-center transition-colors hover:text-ink-black" aria-label="Início">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10" />
        </svg>
    </a>

    @foreach ($items as $item)
        <svg class="h-3.5 w-3.5 shrink-0 text-border-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>

        @if (! empty($item['xLabel']))
            {{-- Segmento dinâmico (Alpine) --}}
            @if (! empty($item['xUrl']) && ! $loop->last)
                <a :href="{{ $item['xUrl'] }}" class="transition-colors hover:text-ink-black" x-text="{{ $item['xLabel'] }}"></a>
            @else
                <span class="font-medium text-ink-black" aria-current="page" x-text="{{ $item['xLabel'] }}"></span>
            @endif
        @else
            {{-- Segmento estático --}}
            @if (! empty($item['url']) && ! $loop->last)
                <a href="{{ $item['url'] }}" class="transition-colors hover:text-ink-black">{{ $item['label'] }}</a>
            @else
                <span class="font-medium text-ink-black" aria-current="page">{{ $item['label'] }}</span>
            @endif
        @endif
    @endforeach
</nav>
