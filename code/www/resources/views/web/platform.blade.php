@extends('layouts.site')

@section('title', 'Plataforma')
@section('description', 'Como o produto funciona — os principais recursos e o fluxo de valor.')

@section('content')
    <x-site.section eyebrow="A plataforma" title="Como o {{ config('site.product.name') }} funciona" center
        subtitle="Apresente o produto por partes: o problema, a solução e o resultado.">
    </x-site.section>

    @php
        $steps = [
            ['title' => 'Comece em minutos', 'body' => 'Explique o onboarding — como o cliente sai do zero ao primeiro valor rapidamente.'],
            ['title' => 'Trabalhe do seu jeito', 'body' => 'Descreva o recurso central da plataforma e por que ele é melhor do que a alternativa.'],
            ['title' => 'Cresça com confiança', 'body' => 'Fale de escala, segurança, integrações — o que sustenta o cliente no longo prazo.'],
        ];
    @endphp

    @foreach ($steps as $i => $step)
        <section class="py-8">
            <x-site.container>
                <div class="grid items-center gap-10 md:grid-cols-2">
                    <div @class(['md:order-2' => $i % 2 === 1])>
                        <p class="text-sm font-semibold uppercase tracking-wide text-brand">Passo {{ $i + 1 }}</p>
                        <h2 class="mt-2 text-3xl font-semibold text-ink">{{ $step['title'] }}</h2>
                        <p class="mt-4 text-lg leading-relaxed text-ink-mid">{{ $step['body'] }}</p>
                    </div>
                    <div @class(['md:order-1' => $i % 2 === 1])>
                        <div class="flex aspect-[4/3] items-center justify-center rounded-2xl border border-line-soft bg-well text-sm text-ink-low">
                            Imagem / captura do recurso
                        </div>
                    </div>
                </div>
            </x-site.container>
        </section>
    @endforeach

    {{-- CTA --}}
    <section class="py-16 sm:py-24">
        <x-site.container class="text-center">
            <h2 class="text-3xl font-semibold text-ink sm:text-4xl">Veja na prática</h2>
            <p class="mx-auto mt-3 max-w-xl text-ink-mid">Crie sua conta e explore a plataforma agora.</p>
            <div class="mt-8">
                <x-site.button :href="config('site.app_url')" size="lg">Começar grátis</x-site.button>
            </div>
        </x-site.container>
    </section>
@endsection
