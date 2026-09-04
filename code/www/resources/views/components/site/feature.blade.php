{{-- Card de feature: ícone (slot opcional) + título + descrição (slot). --}}
@props(['title' => null])

<div class="rounded-xl border border-line-soft bg-canvas-raised p-6">
    @isset($icon)
        <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-well text-ink">
            {{ $icon }}
        </div>
    @endisset
    <h3 class="text-lg font-semibold text-ink">{{ $title }}</h3>
    <p class="mt-2 text-sm leading-relaxed text-ink-mid">{{ $slot }}</p>
</div>
