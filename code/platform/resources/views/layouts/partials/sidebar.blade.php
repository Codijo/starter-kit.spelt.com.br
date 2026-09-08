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

        {{-- ── Seções do produto ────────────────────────────────────────────
             Acordeão exclusivo: uma seção aberta por vez. O cabeçalho leva ao hub da
             seção; o chevron só abre/fecha. Sub-cabeçalho (`group`) subdivide a lista
             sem virar página.

             Cresce por DADO, não por markup: acrescente a entrada em $sections e a
             seção aparece. Item sem rota registrada é ignorado, então dá para declarar
             a navegação inteira antes das telas existirem. --}}
        @php
            // EXEMPLO — troque pelas seções do seu produto. Declare a navegação
            // INTEIRA já: item cuja rota ainda não existe é ignorado, então o menu
            // cresce sozinho conforme as telas vão sendo entregues.
            //
            //   'hub'      rota do cabeçalho da seção (o clique no nome)
            //   'children' itens; 'pattern' marca o ativo, 'group' cria sub-cabeçalho
            $sections = [
                'catalog' => [
                    'label' => 'Cadastro',
                    'hub' => 'catalog.items',
                    'icon' => ['M3.75 21h16.5M4.5 3h9.75c.414 0 .75.336.75.75V21H4.5V3.75A.75.75 0 0 1 5.25 3ZM15 9.75h3.75c.414 0 .75.336.75.75V21H15V9.75Z'],
                    'children' => [
                        ['route' => 'catalog.items', 'label' => 'Itens', 'pattern' => 'catalog.items*', 'group' => 'Estrutura'],
                    ],
                ],
            ];

            // Resolve rota, URL e estado ativo. Rota inexistente = item some da nav.
            $navItem = function (array $item) {
                $exists = \Illuminate\Support\Facades\Route::has($item['route']);
                return [
                    'exists' => $exists,
                    'url' => $exists ? route($item['route']) : '#',
                    'label' => $item['label'],
                    'active' => $exists && request()->routeIs($item['pattern'] ?? $item['route']),
                ];
            };

            // Seção aberta no primeiro paint = a da tela atual.
            $activeSection = null;
            foreach ($sections as $key => $section) {
                foreach ($section['children'] as $child) {
                    if (\Illuminate\Support\Facades\Route::has($child['route']) && request()->routeIs($child['pattern'] ?? $child['route'])) {
                        $activeSection = $key;
                        break 2;
                    }
                }
            }
        @endphp

        <div x-show="!collapsed" x-cloak x-data="{ open: @js($activeSection), toggle(k) { this.open = this.open === k ? null : k } }" class="pt-2">
            @foreach ($sections as $sectionKey => $section)
                @php
                    $visible = collect($section['children'])->filter(fn ($c) => \Illuminate\Support\Facades\Route::has($c['route']));
                @endphp
                @if ($visible->isNotEmpty())
                    @php $hub = $navItem(['route' => $section['hub'], 'label' => $section['label'], 'pattern' => null]); @endphp
                    <div class="mb-0.5">
                        <div class="flex items-center gap-0.5">
                            <a href="{{ $hub['url'] }}"
                               class="{{ $activeSection === $sectionKey ? 'font-semibold text-ink-black' : 'font-medium text-linear-gray-dark hover:text-ink-black' }} group flex min-w-0 flex-1 items-center gap-3 rounded-md px-3 py-2 transition-colors hover:bg-subtle-ash">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    @foreach ($section['icon'] as $d)
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $d }}" />
                                    @endforeach
                                </svg>
                                <span class="truncate">{{ $section['label'] }}</span>
                            </a>
                            <button type="button" @click="toggle('{{ $sectionKey }}')"
                                    :aria-expanded="open === '{{ $sectionKey }}' ? 'true' : 'false'"
                                    aria-label="Expandir {{ $section['label'] }}"
                                    class="shrink-0 rounded-md p-1.5 text-linear-gray-light transition-colors hover:bg-subtle-ash hover:text-ink-black">
                                <svg class="h-3 w-3 transition-transform duration-200" :class="{ '-rotate-90': open !== '{{ $sectionKey }}' }"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>

                        {{-- Sem x-cloak de propósito: o `style` server-side já entrega o estado
                             certo no primeiro paint; x-cloak esconderia a seção ativa até o
                             Alpine bootar — justamente o flash que se quer evitar. --}}
                        <ul x-show="open === '{{ $sectionKey }}'" @if ($activeSection !== $sectionKey) style="display: none;" @endif
                            x-collapse
                            class="mb-1.5 ml-5 mt-1 space-y-0.5 border-l border-border-light pl-3">
                            @php $lastGroup = null; @endphp
                            @foreach ($section['children'] as $child)
                                @php $n = $navItem($child); @endphp
                                @if ($n['exists'])
                                    @php $group = $child['group'] ?? null; @endphp
                                    @if ($group !== $lastGroup)
                                        @php $lastGroup = $group; @endphp
                                        @if ($group)
                                            <li class="{{ $loop->first ? '' : 'mt-1.5 border-t border-border-light pt-1.5' }} px-2 pb-1">
                                                <span class="text-[10px] font-semibold uppercase tracking-wide text-linear-gray-light">{{ $group }}</span>
                                            </li>
                                        @endif
                                    @endif
                                    <li>
                                        <a href="{{ $n['url'] }}"
                                           class="{{ $n['active'] ? 'bg-subtle-ash font-semibold text-ink-black' : 'font-medium text-linear-gray-dark hover:bg-subtle-ash hover:text-ink-black' }} block w-full truncate rounded-md px-2 py-1.5 transition-colors">
                                            {{ $n['label'] }}
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Fora do acordeão, como o "Início": é uma tela só, e atravessa todas as seções —
             exporta-se Lead, Contato e Negócio pelo mesmo lugar. Enfiá-la dentro de uma
             seção obrigaria a escolher uma delas, e as outras duas ficariam sem caminho. --}}
        @if (Route::has('export.index'))
            <a href="{{ route('export.index') }}"
               class="mt-1 flex items-center gap-3 rounded-md px-3 py-2 font-medium {{ request()->routeIs('export.*') ? 'bg-subtle-ash text-ink-black' : 'text-linear-gray-dark hover:bg-subtle-ash' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                <span x-show="!collapsed">Exportações</span>
            </a>
        @endif

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
