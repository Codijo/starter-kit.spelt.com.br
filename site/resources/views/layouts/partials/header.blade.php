<header class="sticky top-0 z-40 border-b border-line-soft bg-canvas/90 backdrop-blur" x-data="{ open: false }">
    <x-site.container class="flex h-16 items-center justify-between gap-4">
        <a href="{{ route('site.home') }}" class="flex items-center" aria-label="{{ config('site.product.name') }}">
            <x-site.logo />
        </a>

        {{-- Navegação (desktop) --}}
        <nav class="hidden items-center gap-1 md:flex">
            @foreach (config('site.nav') as $item)
                <x-site.nav-link :route="$item['route']">{{ $item['label'] }}</x-site.nav-link>
            @endforeach
        </nav>

        {{-- CTAs (desktop) --}}
        <div class="hidden items-center gap-3 md:flex">
            <a href="{{ config('site.app_url') }}" class="text-sm font-medium text-ink-mid transition-colors hover:text-ink">Entrar</a>
            <x-site.button :href="config('site.app_url')" size="sm">Começar</x-site.button>
        </div>

        {{-- Toggle (mobile) --}}
        <button type="button" @click="open = !open" class="rounded-md p-2 text-ink md:hidden" aria-label="Menu">
            <svg x-show="!open" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
            <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </x-site.container>

    {{-- Menu (mobile) --}}
    <div x-show="open" x-collapse x-cloak class="border-t border-line-soft bg-canvas md:hidden">
        <x-site.container class="space-y-1 py-3">
            @foreach (config('site.nav') as $item)
                <a href="{{ route($item['route']) }}" class="block rounded-md px-3 py-2 text-sm font-medium text-ink-mid hover:bg-well hover:text-ink">{{ $item['label'] }}</a>
            @endforeach
            <div class="flex gap-2 pt-2">
                <x-site.button :href="config('site.app_url')" variant="secondary" class="flex-1">Entrar</x-site.button>
                <x-site.button :href="config('site.app_url')" class="flex-1">Começar</x-site.button>
            </div>
        </x-site.container>
    </div>
</header>
