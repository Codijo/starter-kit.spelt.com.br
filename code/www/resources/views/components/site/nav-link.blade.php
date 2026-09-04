@props(['route'])
@php $active = request()->routeIs($route); @endphp

<a href="{{ route($route) }}" @class([
    'rounded-full px-3 py-2 text-sm font-medium transition-colors',
    'text-ink' => $active,
    'text-ink-mid hover:text-ink' => ! $active,
])>{{ $slot }}</a>
