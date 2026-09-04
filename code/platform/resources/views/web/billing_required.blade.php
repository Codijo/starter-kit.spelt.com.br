@extends('layouts.guard')

@section('title', 'Assinatura necessária')

@section('content')
    <div class="mx-auto max-w-md rounded-xl border border-border-light bg-canvas-white p-8 text-center shadow-sm">
        <h1 class="font-title text-xl font-semibold text-ink-black">Assinatura necessária</h1>
        <p class="mt-2 text-sm text-linear-gray-dark">
            Seu acesso está bloqueado porque a assinatura não está ativa.
        </p>
        <button @click="$store.me.managePortal()" class="mt-6 rounded-md bg-accent-blue px-4 py-2 text-sm font-medium text-canvas-white hover:opacity-90">
            Gerenciar assinatura
        </button>
        <p x-show="$store.me.error" x-cloak class="mt-4 text-sm text-warm-orange" x-text="$store.me.error"></p>
    </div>
@endsection
