{{--
  Host único de mensagens transitórias (store `flash`). Portado do App do Spelt.
  Fica uma vez no layout — nenhuma página inclui, nenhuma esquece. Renderiza acima de
  qualquer modal (pilha z-[100]; bloqueante z-[110]).
  Uso: $store.flash.success('...') · .error(msg) · .validation(errosDoLaravel).
--}}

@php
  $iconPaths = [
      'success' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
      'info' => 'm11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
      'warning' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
      'error' => 'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z',
      'validation' => 'M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75',
  ];
@endphp

<div x-data="{ stackOpen: false }" x-cloak>

  {{-- Pilha de toasts — bottom-right no desktop, faixa inferior no mobile --}}
  <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[100] flex flex-col gap-2 p-4 sm:inset-x-auto sm:right-0 sm:w-[400px]"
       aria-live="polite" aria-atomic="false">

    {{-- "+N anteriores" quando a pilha estoura o maxVisible --}}
    <template x-if="$store.flash.hiddenCount() > 0 && !stackOpen">
      <button type="button" @click="stackOpen = true"
        class="pointer-events-auto self-end rounded-full border border-border-light bg-canvas-white px-3 py-1 text-xs font-medium text-steel-gray shadow-subtle transition-colors hover:bg-subtle-ash hover:text-ink-black">
        <span x-text="'+' + $store.flash.hiddenCount()"></span>
        <span x-text="$store.flash.hiddenCount() === 1 ? 'mensagem anterior' : 'mensagens anteriores'"></span>
      </button>
    </template>

    <template x-if="stackOpen && $store.flash.items.length > $store.flash.maxVisible">
      <button type="button" @click="stackOpen = false"
        class="pointer-events-auto self-end rounded-full border border-border-light bg-canvas-white px-3 py-1 text-xs font-medium text-steel-gray shadow-subtle transition-colors hover:bg-subtle-ash hover:text-ink-black">
        Recolher
      </button>
    </template>

    <template x-for="item in (stackOpen ? $store.flash.items : $store.flash.visible())" :key="item.id">
      <div
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-2 opacity-0 sm:translate-x-4 sm:translate-y-0"
        x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @mouseenter="$store.flash.pause(item.id)"
        @mouseleave="$store.flash.resume(item.id)"
        :role="item.type === 'error' || item.type === 'validation' ? 'alert' : 'status'"
        class="pointer-events-auto overflow-hidden rounded-lg border border-border-light border-l-4 bg-canvas-white shadow-lg"
        :class="{
            'border-l-fresh-green': item.type === 'success',
            'border-l-accent-blue': item.type === 'info',
            'border-l-amber-500': item.type === 'warning',
            'border-l-red-500': item.type === 'error' || item.type === 'validation',
        }">

        <div class="flex items-start gap-3 p-4">
          <div class="mt-0.5 shrink-0"
            :class="{
                'text-fresh-green': item.type === 'success',
                'text-accent-blue': item.type === 'info',
                'text-amber-500': item.type === 'warning',
                'text-red-500': item.type === 'error' || item.type === 'validation',
            }">
            @foreach ($iconPaths as $type => $path)
              <svg x-show="item.type === '{{ $type }}'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
              </svg>
            @endforeach
          </div>

          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <p class="text-sm font-semibold text-ink-black" x-text="item.title"></p>
              <span x-show="item.count > 1"
                class="rounded-full bg-subtle-ash px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-steel-gray"
                x-text="'×' + item.count"></span>
            </div>

            <div class="relative mt-1">
              <div
                x-init="$nextTick(() => { item.clamped = $el.scrollHeight > $el.clientHeight + 2 })"
                :class="item.expanded ? 'max-h-64 overflow-y-auto' : 'max-h-20 overflow-hidden'"
                class="toast-body text-sm leading-relaxed text-steel-gray"
                x-html="item.message"></div>
              <div x-show="item.clamped && !item.expanded"
                class="pointer-events-none absolute inset-x-0 bottom-0 h-6 bg-gradient-to-t from-canvas-white to-transparent"></div>
            </div>

            <button x-show="item.clamped" type="button" @click="$store.flash.toggleExpanded(item.id)"
              class="mt-1.5 text-xs font-semibold text-accent-blue transition-opacity hover:opacity-80"
              x-text="item.expanded ? 'Ver menos' : 'Ver mais'"></button>
          </div>

          <button type="button" @click="$store.flash.dismiss(item.id)"
            class="-mr-1 -mt-1 shrink-0 rounded-md p-1 text-steel-gray transition-colors hover:bg-subtle-ash hover:text-ink-black focus:outline-none focus:ring-2 focus:ring-accent-blue/30"
            aria-label="Fechar mensagem">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </template>
  </div>

  {{-- Bloqueante — flash.error(msg, { blocking: true }) --}}
  <div x-show="$store.flash.blocker" x-transition.opacity
    class="fixed inset-0 z-[110] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
    @keydown.escape.window="$store.flash.dismissBlocker()" role="alertdialog" aria-modal="true">

    <div x-show="$store.flash.blocker" @click.outside="$store.flash.dismissBlocker()"
      x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="scale-95 opacity-0"
      x-transition:enter-end="scale-100 opacity-100"
      class="w-full max-w-md overflow-hidden rounded-lg border border-border-light bg-canvas-white shadow-lg">

      <div class="p-6">
        <div class="flex items-start gap-4">
          <div class="mt-0.5 shrink-0"
            :class="{
                'text-fresh-green': $store.flash.blocker?.type === 'success',
                'text-accent-blue': $store.flash.blocker?.type === 'info',
                'text-amber-500': $store.flash.blocker?.type === 'warning',
                'text-red-500': $store.flash.blocker?.type === 'error' || $store.flash.blocker?.type === 'validation',
            }">
            @foreach ($iconPaths as $type => $path)
              <svg x-show="$store.flash.blocker?.type === '{{ $type }}'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
              </svg>
            @endforeach
          </div>

          <div class="min-w-0 flex-1">
            <h2 class="font-display text-lg font-medium text-ink-black" x-text="$store.flash.blocker?.title"></h2>
            <div class="toast-body mt-2 max-h-64 overflow-y-auto text-sm leading-relaxed text-steel-gray"
              x-html="$store.flash.blocker?.message"></div>
          </div>
        </div>
      </div>

      <div class="flex justify-end border-t border-border-light bg-subtle-ash px-6 py-4">
        <button type="button" @click="$store.flash.dismissBlocker()"
          class="inline-flex items-center gap-2 rounded-md bg-ink-black px-4 py-2.5 text-sm font-semibold text-canvas-white shadow-subtle transition-colors hover:bg-thunder-gray focus:outline-none focus:ring-2 focus:ring-ink-black focus:ring-offset-2">
          Entendi
        </button>
      </div>
    </div>
  </div>
</div>
