{{-- Header — toggle da sidebar + nome da conta + menu do usuário. --}}
<header class="flex h-16 shrink-0 items-center justify-between border-b border-border-light bg-canvas-white px-6">
    <div class="flex items-center gap-3">
        <button @click="collapsed = !collapsed" class="rounded-md p-1.5 text-linear-gray-dark hover:bg-subtle-ash" aria-label="Alternar menu">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
        </button>
        <div class="font-title font-semibold text-ink-black" x-text="$store.me.account?.name || {{ Illuminate\Support\Js::from(config('services.product.name')) }}"></div>
    </div>

    <div class="flex items-center gap-4">
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" @click.away="open = false" class="flex items-center gap-2 rounded-md p-1 hover:bg-subtle-ash">
                <span class="grid h-8 w-8 place-items-center rounded-full bg-ink-black text-xs font-medium text-canvas-white" x-text="$store.me.initials()"></span>
                <span class="hidden text-sm text-ink-black sm:block" x-text="$store.me.user?.name || '—'"></span>
                <svg class="h-4 w-4 text-linear-gray-light" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
            </button>

            <div x-show="open" x-cloak x-transition.origin.top.right
                 class="absolute right-0 mt-2 w-60 rounded-lg border border-border-light bg-canvas-white py-1 shadow-md">
                <div class="border-b border-border-light px-4 py-2">
                    <div class="truncate text-sm font-medium text-ink-black" x-text="$store.me.user?.name"></div>
                    <div class="truncate text-xs text-linear-gray-light" x-text="$store.me.user?.email"></div>
                </div>
                <button @click="$store.me.managePortal()" class="block w-full px-4 py-2 text-left text-sm text-linear-gray-dark hover:bg-subtle-ash">
                    Minha conta
                </button>
                <a href="{{ route('logout') }}" class="block px-4 py-2 text-sm text-linear-gray-dark hover:bg-subtle-ash">
                    Sair
                </a>
            </div>
        </div>
    </div>
</header>
