{{--
  Cabeçalho padrão de tela: ícone (slot, opcional) + título (slot default) + badges + meta + ações.
  Uso:
    <x-layout.page-header>
        <x-slot:icon><svg …></svg></x-slot:icon>
        <span x-text="item.name || 'Marca'"></span>
        <x-slot:badges><span x-show="!loading" x-cloak><x-ui.status-badge value="item.status" /></span></x-slot:badges>
        <x-slot:meta>Contexto salvo da marca.</x-slot:meta>
        <x-slot:actions><button @click="openEdit()">Editar</button></x-slot:actions>
    </x-layout.page-header>
--}}
<div class="flex flex-wrap items-start justify-between gap-4 border-b border-border-light pb-4">
    <div class="flex min-w-0 items-start gap-3">
        @isset($icon)
            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-ink-black text-canvas-white">
                {{ $icon }}
            </div>
        @endisset
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-title text-2xl font-semibold text-ink-black">{{ $slot }}</h1>
                @isset($badges)
                    {{ $badges }}
                @endisset
            </div>
            @isset($meta)
                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-linear-gray-dark">{{ $meta }}</div>
            @endisset
        </div>
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
