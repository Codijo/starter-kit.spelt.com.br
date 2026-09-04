@extends('layouts.site')

@section('description', 'Página inicial — apresente o produto e o valor central.')

@section('content')
    {{-- Hero --}}
    <section class="py-20 sm:py-28">
        <x-site.container class="text-center">
            <p class="text-sm font-semibold uppercase tracking-wide text-brand">{{ config('site.product.name') }}</p>
            <h1 class="mx-auto mt-4 max-w-3xl text-4xl font-semibold leading-[1.1] text-ink sm:text-6xl">
                {{ config('site.product.tagline') }}
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-ink-mid">
                Explique em uma frase o que o produto resolve e para quem. Direto ao ponto.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <x-site.button :href="config('site.app_url')" size="lg">Começar grátis</x-site.button>
                <x-site.button :href="route('site.platform')" variant="secondary" size="lg">Conhecer a plataforma</x-site.button>
            </div>

            {{-- "Poço" de screenshot — troque pela imagem do produto --}}
            <div class="mx-auto mt-14 max-w-4xl rounded-2xl border border-line-soft bg-well p-2 shadow-lg">
                <div class="flex aspect-video items-center justify-center rounded-xl bg-canvas-raised text-sm text-ink-low">
                    Imagem do produto
                </div>
            </div>
        </x-site.container>
    </section>

    {{-- Features --}}
    <x-site.section eyebrow="Por que" title="O que torna o produto diferente" center
        subtitle="Três motivos para escolher — troque pelos seus diferenciais reais.">
        <div class="grid gap-6 md:grid-cols-3">
            <x-site.feature title="Rápido de começar">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/></svg>
                </x-slot:icon>
                Descreva o primeiro benefício em uma ou duas frases claras.
            </x-site.feature>
            <x-site.feature title="Simples de usar">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </x-slot:icon>
                Descreva o segundo benefício — foque no resultado para o cliente.
            </x-site.feature>
            <x-site.feature title="Feito para crescer">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/></svg>
                </x-slot:icon>
                Descreva o terceiro benefício — o que o produto habilita no futuro.
            </x-site.feature>
        </div>
    </x-site.section>

    {{-- Planos (resumo) --}}
    <x-site.section eyebrow="Planos" title="Preço simples e transparente" center>
        <div class="grid gap-6 md:grid-cols-3">
            @foreach (config('site.plans') as $plan)
                <x-site.pricing-card :plan="$plan" />
            @endforeach
        </div>
        <p class="mt-8 text-center text-sm text-ink-mid">
            <a href="{{ route('site.pricing') }}" class="font-medium text-brand hover:underline">Ver detalhes dos planos →</a>
        </p>
    </x-site.section>

    {{-- CTA final --}}
    <section class="py-16 sm:py-24">
        <x-site.container>
            <div class="rounded-2xl bg-ink px-6 py-14 text-center sm:px-12">
                <h2 class="text-3xl font-semibold text-white sm:text-4xl">Pronto para começar?</h2>
                <p class="mx-auto mt-3 max-w-xl text-white/70">Crie sua conta em minutos — sem cartão de crédito.</p>
                <div class="mt-8">
                    <x-site.button :href="config('site.app_url')" size="lg">Criar conta</x-site.button>
                </div>
            </div>
        </x-site.container>
    </section>
@endsection
