@extends('layouts.guard')

@section('title', 'Painel')

@section('content')
    <div>
        <h1 class="font-title text-2xl font-semibold text-ink-black">Painel</h1>
        <p class="mt-1 text-sm text-linear-gray-dark">Visão geral da sua conta.</p>

        <div x-show="$store.me.loading" class="mt-8 text-sm text-linear-gray-dark">Carregando…</div>
        <div x-show="$store.me.error" x-cloak
             class="mt-8 rounded-md border border-warm-orange/30 bg-warm-orange/10 px-4 py-3 text-sm text-warm-orange"
             x-text="$store.me.error"></div>

        <template x-if="$store.me.loaded">
            <div class="mt-8 space-y-6">
                {{-- Conta/Plano/Créditos/Acesso e Entitlements vivem no portal Customer do Spelt
                     (acessível pelo nudge de conversão / "Gerenciar assinatura"). A home do produto
                     foca no trabalho de valor — não repita billing aqui. --}}

                {{-- Placeholder do produto --}}
                <div class="rounded-lg border border-dashed border-border-muted bg-canvas-white/40 p-10 text-center">
                    <p class="text-sm font-medium text-linear-gray-dark">Área do produto</p>
                    <p class="mt-1 text-sm text-linear-gray-light">Substitua por suas telas de valor (listagens, formulários) no padrão do App, isolando por <code>forCurrentAccount</code>.</p>
                </div>
            </div>
        </template>
    </div>
@endsection
