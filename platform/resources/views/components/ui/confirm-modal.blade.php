{{--
  Host único do modal de confirmação (store `confirm`). Fica uma vez no layout.
  Substitui o window.confirm() do navegador. Abrir via $store.confirm.open({...}).
--}}
<div x-data x-show="$store.confirm.isOpen" x-cloak
     class="fixed inset-0 z-[105] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
     @keydown.escape.window="$store.confirm.close()"
     role="alertdialog" aria-modal="true">

    <div x-show="$store.confirm.isOpen" @click.outside="$store.confirm.close()"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="scale-95 opacity-0"
         x-transition:enter-end="scale-100 opacity-100"
         class="w-full max-w-md overflow-hidden rounded-lg border border-border-light bg-canvas-white shadow-lg">

        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="mt-0.5 shrink-0" :class="$store.confirm.danger ? 'text-red-500' : 'text-accent-blue'">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="font-display text-lg font-medium text-ink-black" x-text="$store.confirm.title"></h2>
                    <p class="mt-2 text-sm leading-relaxed text-steel-gray" x-text="$store.confirm.message"></p>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-border-light bg-subtle-ash px-6 py-4">
            <button type="button" @click="$store.confirm.close()" :disabled="$store.confirm.loading"
                class="rounded-md border border-border-muted bg-canvas-white px-4 py-2 text-sm font-medium text-steel-gray transition-colors hover:bg-subtle-ash disabled:opacity-60"
                x-text="$store.confirm.cancelText"></button>
            <button type="button" @click="$store.confirm.accept()" :disabled="$store.confirm.loading"
                class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold text-canvas-white shadow-subtle transition-colors disabled:opacity-60"
                :class="$store.confirm.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-jet-black hover:bg-thunder-gray'">
                <svg x-show="$store.confirm.loading" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span x-text="$store.confirm.loading ? 'Processando…' : $store.confirm.confirmText"></span>
            </button>
        </div>
    </div>
</div>
