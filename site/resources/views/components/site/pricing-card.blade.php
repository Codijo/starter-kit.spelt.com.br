{{-- Card de plano. Espera `plan` = item de config('site.plans'). --}}
@props(['plan'])
@php
    $highlight = $plan['highlight'] ?? false;
    $ctaHref = ! empty($plan['cta_route']) ? route($plan['cta_route']) : config('site.app_url');
@endphp

<div @class([
    'flex flex-col rounded-2xl border p-6',
    'border-brand bg-canvas-raised shadow-md ring-1 ring-brand/20' => $highlight,
    'border-line-soft bg-canvas-raised' => ! $highlight,
])>
    @if ($highlight)
        <span class="mb-3 inline-flex w-fit rounded-full bg-brand px-3 py-1 text-xs font-semibold text-white">Recomendado</span>
    @endif

    <h3 class="text-lg font-semibold text-ink">{{ $plan['name'] }}</h3>
    <p class="mt-1 text-sm text-ink-mid">{{ $plan['description'] }}</p>

    <div class="mt-4 flex items-baseline gap-1">
        <span class="font-display text-4xl font-semibold text-ink">{{ $plan['price'] }}</span>
        @if (! empty($plan['period']))
            <span class="text-sm text-ink-low">{{ $plan['period'] }}</span>
        @endif
    </div>

    <ul class="mt-6 space-y-3 text-sm text-ink-mid">
        @foreach ($plan['features'] as $feature)
            <li class="flex items-start gap-2">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                <span>{{ $feature }}</span>
            </li>
        @endforeach
    </ul>

    <div class="mt-8 pt-2">
        <x-site.button :href="$ctaHref" :variant="$highlight ? 'primary' : 'secondary'" class="w-full">{{ $plan['cta'] }}</x-site.button>
    </div>
</div>
