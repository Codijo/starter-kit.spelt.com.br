{{--
  Título de página (padrão App). Uso: <x-layout.title title="Marcas" subtitle="..." />
  O slot livre à direita serve para ações (botões).
--}}
@props(['title', 'subtitle' => null])

<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="font-title text-2xl font-semibold text-ink-black">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-linear-gray-dark">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex shrink-0 items-center gap-2">{{ $slot }}</div>
    @endif
</div>
