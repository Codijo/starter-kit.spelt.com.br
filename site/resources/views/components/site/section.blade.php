{{-- Seção com espaçamento vertical + cabeçalho opcional (eyebrow/título/subtítulo). --}}
@props(['eyebrow' => null, 'title' => null, 'subtitle' => null, 'center' => false])

<section {{ $attributes->merge(['class' => 'py-16 sm:py-24']) }}>
    <x-site.container>
        @if ($eyebrow || $title || $subtitle)
            <div @class(['max-w-2xl', 'mx-auto text-center' => $center])>
                @if ($eyebrow)
                    <p class="text-sm font-semibold uppercase tracking-wide text-brand">{{ $eyebrow }}</p>
                @endif
                @if ($title)
                    <h2 class="mt-2 text-3xl font-semibold text-ink sm:text-4xl">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-4 text-lg text-ink-mid">{{ $subtitle }}</p>
                @endif
            </div>
        @endif

        <div @class(['mt-12' => $eyebrow || $title || $subtitle])>
            {{ $slot }}
        </div>
    </x-site.container>
</section>
