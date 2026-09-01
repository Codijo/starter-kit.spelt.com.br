{{--
  Seção colapsável (card) para segmentar dados numa tela de detalhe. Padrão App.
  Uso:
    <x-layout.section-card title="Dados da marca">
        <x-slot:badge>…opcional…</x-slot:badge>
        …conteúdo…
    </x-layout.section-card>
  `open` = começa aberta (default true).
--}}
@props(['title' => null, 'open' => true])

<div x-data="{ open: {{ $open ? 'true' : 'false' }} }"
    class="overflow-hidden rounded-lg border border-border-light bg-canvas-white shadow-subtle">
    <button type="button" @click="open = !open"
        class="flex w-full items-center justify-between px-5 py-4 text-left">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold text-ink-black">{{ $title }}</h3>
            @isset($badge)
                {{ $badge }}
            @endisset
        </div>
        <svg class="h-4 w-4 text-steel-gray transition-transform" :class="open ? 'rotate-180' : ''"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" />
        </svg>
    </button>
    <div x-show="open" x-collapse class="border-t border-border-light px-5 pb-5 pt-4">
        {{ $slot }}
    </div>
</div>
