<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $pageTitle = trim($__env->yieldContent('title'));
        $fullTitle = $pageTitle ? $pageTitle.' · '.config('site.product.name') : config('site.product.name');
        $description = trim($__env->yieldContent('description')) ?: config('site.product.tagline');
    @endphp

    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $description }}">

    {{-- Open Graph / redes sociais --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('site.product.name') }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="/favicon.ico">
    @vite('resources/css/app.css')
    @stack('head')
</head>
<body class="min-h-screen bg-canvas font-body text-ink-mid antialiased">
    @include('layouts.partials.header')

    <main>
        @yield('content')
    </main>

    @include('layouts.partials.footer')

    @vite('resources/js/app.js')
    @stack('scripts')
</body>
</html>
