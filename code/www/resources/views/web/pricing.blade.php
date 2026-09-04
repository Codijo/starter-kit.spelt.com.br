@extends('layouts.site')

@section('title', 'Preços')
@section('description', 'Planos e preços — escolha o ideal para o seu momento.')

@section('content')
    <x-site.section eyebrow="Preços" title="Escolha o plano ideal" center
        subtitle="Sem surpresas. Faça upgrade, downgrade ou cancele quando quiser.">
        <div class="grid gap-6 lg:grid-cols-3">
            @foreach (config('site.plans') as $plan)
                <x-site.pricing-card :plan="$plan" />
            @endforeach
        </div>
    </x-site.section>

    {{-- FAQ --}}
    @php
        $faqs = [
            ['q' => 'Posso trocar de plano depois?', 'a' => 'Sim. Você pode fazer upgrade ou downgrade a qualquer momento — a cobrança é ajustada proporcionalmente.'],
            ['q' => 'Existe período de teste?', 'a' => 'Descreva aqui a política de trial do seu produto.'],
            ['q' => 'Como funciona o cancelamento?', 'a' => 'Você cancela quando quiser, direto no painel, sem burocracia.'],
            ['q' => 'Quais formas de pagamento?', 'a' => 'Liste as formas de pagamento aceitas pelo seu produto.'],
        ];
    @endphp
    <x-site.section title="Perguntas frequentes" center>
        <div class="mx-auto max-w-2xl divide-y divide-line-soft">
            @foreach ($faqs as $faq)
                <div x-data="{ open: false }" class="py-4">
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-4 text-left">
                        <span class="font-medium text-ink">{{ $faq['q'] }}</span>
                        <svg class="h-5 w-5 shrink-0 text-ink-low transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse x-cloak>
                        <p class="pt-3 text-sm leading-relaxed text-ink-mid">{{ $faq['a'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </x-site.section>
@endsection
