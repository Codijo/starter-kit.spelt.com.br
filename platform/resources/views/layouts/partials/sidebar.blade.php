{{-- Sidebar (padrão App). O produto substitui/estende a seção "Seu produto". --}}
<aside class="flex flex-col border-r border-border-light bg-canvas-white transition-all duration-200"
       :class="collapsed ? 'w-16' : 'w-64'">

    {{-- Marca do produto --}}
    <div class="flex h-16 items-center gap-2 border-b border-border-light px-4">
        @if (config('services.product.logo'))
            <img src="{{ config('services.product.logo') }}" alt="" class="h-7 w-7 shrink-0 object-contain">
        @else
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-md bg-ink-black text-xs font-bold text-canvas-white">
                {{ strtoupper(substr(config('services.product.name'), 0, 1)) }}
            </span>
        @endif
        <span x-show="!collapsed" class="truncate font-title text-sm font-semibold">{{ config('services.product.name') }}</span>
    </div>

    {{-- Navegação --}}
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
        <a href="{{ route('dashboard') }}"
           class="flex items-center gap-3 rounded-md px-3 py-2 font-medium {{ request()->routeIs('dashboard') ? 'bg-subtle-ash text-ink-black' : 'text-linear-gray-dark hover:bg-subtle-ash' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10"/></svg>
            <span x-show="!collapsed">Início</span>
        </a>

        <div x-show="!collapsed" x-cloak class="px-3 pb-1 pt-4 text-xs font-medium uppercase tracking-wide text-linear-gray-light">Seu produto</div>
        {{-- EXEMPLO — troque por suas telas de valor (rotas do produto) --}}
        <a href="#" class="flex items-center gap-3 rounded-md px-3 py-2 text-linear-gray-dark hover:bg-subtle-ash">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5M3.75 9.75h16.5M3.75 14.25h16.5M3.75 18.75h16.5"/></svg>
            <span x-show="!collapsed">Exemplo — Listagem</span>
        </a>
        <a href="#" class="flex items-center gap-3 rounded-md px-3 py-2 text-linear-gray-dark hover:bg-subtle-ash">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span x-show="!collapsed">Exemplo — Criar</span>
        </a>
    </nav>

    {{-- Conta --}}
    <div class="space-y-1 border-t border-border-light px-3 py-3 text-sm">
        <button @click="$store.me.managePortal()"
                class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-linear-gray-dark hover:bg-subtle-ash">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0"/></svg>
            <span x-show="!collapsed">Minha conta</span>
        </button>
        <a href="{{ route('logout') }}"
           class="flex items-center gap-3 rounded-md px-3 py-2 text-linear-gray-dark hover:bg-subtle-ash">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M18 15l3-3m0 0l-3-3m3 3H9"/></svg>
            <span x-show="!collapsed">Sair</span>
        </a>
    </div>
</aside>
