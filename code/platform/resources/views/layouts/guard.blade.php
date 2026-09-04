<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('services.product.name'))</title>
    @include('layouts.partials.app_bootstrap')
    @vite('resources/css/app.css')
</head>
<body class="h-full bg-subtle-ash font-body text-ink-black antialiased">
    <div class="flex h-full"
         x-data="{ collapsed: $persist(false).as('kit_sidebar_collapsed') }"
         x-init="$store.me.load()">

        @include('layouts.partials.sidebar')

        <div class="flex min-w-0 flex-1 flex-col">
            @include('layouts.partials.header')

            <main class="flex-1 overflow-y-auto">
                @include('layouts.partials.conversion_nudge')
                {{-- Largo (padrão App), não centralizado num column estreito --}}
                <div class="px-6 py-6 lg:px-8">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    {{-- Hosts únicos: mensagens transitórias (flash) + confirmação (confirm) --}}
    <x-ui.toast />
    <x-ui.confirm-modal />

    {{-- app.js no FIM do body (padrão App): roda DEPOIS do @yield('content'), então o
         @vite per-página de cada tela registra seu componente via alpine:init ANTES do
         Alpine.start() que o app.js dispara. --}}
    @vite('resources/js/app.js')
    @stack('scripts')
</body>
</html>
