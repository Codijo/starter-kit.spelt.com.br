@extends('layouts.public')

@section('content')
    <div class="w-full max-w-md rounded-xl border border-border-light bg-canvas-white p-8 text-center shadow-sm">
        <h1 class="font-title text-2xl font-semibold text-ink-black">{{ config('services.product.name') }}</h1>
        <p class="mt-2 text-sm text-linear-gray-dark">Acesse sua conta.</p>

        @if ($errors->any())
            <div class="mt-4 rounded-md border border-warm-orange/30 bg-warm-orange/10 px-3 py-2 text-sm text-warm-orange">
                {{ $errors->first() }}
            </div>
        @endif

        <p class="mt-6 text-xs text-linear-gray-light">
            Você chega aqui já autenticado, a partir do seu painel de assinatura.
        </p>

        @if (app()->isLocal())
            <p class="mt-4 rounded-md bg-subtle-ash px-3 py-2 text-left text-xs text-linear-gray-dark">
                <strong>DEV:</strong> gere um token na API e acesse
                <code>/auth/dev-login?token=SEU_TOKEN</code> para testar sem o SSO real.
            </p>
        @endif
    </div>
@endsection
