@extends('layouts.site')

@section('title', 'Sobre')
@section('description', 'Sobre o produto e a empresa — a missão e os valores.')

@section('content')
    <x-site.section eyebrow="Sobre" title="Nossa missão"
        subtitle="Conte a história: por que o produto existe e no que vocês acreditam.">
        <div class="max-w-3xl space-y-6 text-lg leading-relaxed text-ink-mid">
            <p>Parágrafo 1 — o problema que motivou a criação do produto.</p>
            <p>Parágrafo 2 — como vocês resolvem esse problema e o que os torna diferentes.</p>
            <p>Parágrafo 3 — a visão de futuro e o impacto que buscam.</p>
        </div>
    </x-site.section>

    <x-site.section title="No que acreditamos">
        <div class="grid gap-6 md:grid-cols-3">
            <x-site.feature title="Valor um">Descreva um princípio que guia o produto e o time.</x-site.feature>
            <x-site.feature title="Valor dois">Descreva o segundo princípio — simples e verdadeiro.</x-site.feature>
            <x-site.feature title="Valor três">Descreva o terceiro princípio.</x-site.feature>
        </div>
    </x-site.section>

    <section class="py-16 sm:py-24">
        <x-site.container class="text-center">
            <h2 class="text-3xl font-semibold text-ink sm:text-4xl">Quer conversar?</h2>
            <p class="mx-auto mt-3 max-w-xl text-ink-mid">Adoramos ouvir quem usa o produto.</p>
            <div class="mt-8">
                <x-site.button :href="route('site.contact')" size="lg">Fale com a gente</x-site.button>
            </div>
        </x-site.container>
    </section>
@endsection
