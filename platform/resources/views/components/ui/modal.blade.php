{{--
  Modal genérico = DRAWER à direita (padrão App). Desliza da direita, altura cheia.
  NÃO tem x-data próprio — usa o escopo do componente Alpine que o envolve, lendo/escrevendo
  a variável de estado passada em `state` (ex.: editOpen). Assim o método do componente
  (ex.: save()) fecha o drawer com `this.editOpen = false`.

  Uso (dentro de um x-data que tenha a variável de estado):
    <x-ui.modal state="editOpen" title="Editar marca" size="lg">
      <form @submit.prevent="save()"> ... </form>
    </x-ui.modal>
--}}
@props(['title' => null, 'state' => 'modalOpen', 'size' => 'lg'])

@php
    $widthClass = match ($size) {
        'sm' => 'sm:max-w-md',
        'md' => 'sm:max-w-lg',
        'lg' => 'sm:max-w-xl',
        'xl' => 'sm:max-w-2xl',
        '2xl' => 'sm:max-w-3xl',
        'half' => 'sm:max-w-[50vw]',
        default => 'sm:max-w-xl',
    };
@endphp

{{-- Overlay --}}
<div x-show="{{ $state }}" x-cloak
     @keydown.escape.window="{{ $state }} = false"
     x-transition:enter="transition-opacity ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-[105] flex justify-end bg-black/50 backdrop-blur-sm"
     role="dialog" aria-modal="true">

    {{-- Drawer (direita) --}}
    <div @click.outside="{{ $state }} = false"
         x-show="{{ $state }}"
         x-transition:enter="transform transition ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="flex h-full w-full {{ $widthClass }} flex-col border-l border-border-light bg-canvas-white shadow-2xl">

        <div class="flex shrink-0 items-center justify-between border-b border-border-light px-6 py-4">
            <h3 class="font-display text-lg font-semibold text-ink-black">{{ $title }}</h3>
            <button type="button" @click="{{ $state }} = false"
                class="rounded-md p-1.5 text-steel-gray transition-colors hover:bg-subtle-ash hover:text-ink-black"
                aria-label="Fechar">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </div>
    </div>
</div>
