@props([
    'label' => 'Copiar',   // Rótulo em repouso
    'value',               // Expressão Alpine com o texto a copiar
    'show' => null,        // Expressão Alpine; ausente = sempre visível
])

{{--
  Linha de "copiar para a área de transferência" das Ações Rápidas.

  O rótulo vira "Copiado!" por 2,5s — confirmação no próprio botão, onde o olho já
  está. O `window.copyText` (resources/js/modules/helpers.js) resolve HTTP e HTTPS:
  usa a API do navegador quando disponível e cai para `execCommand` quando não,
  o que importa porque o ambiente de desenvolvimento não roda em HTTPS.
--}}

<div x-data="{ copied: false }" @if ($show) x-show="{{ $show }}" @endif>
  <button type="button"
    @click="copyText({{ $value }}).then((ok) => { if (ok) { copied = true; setTimeout(() => copied = false, 2500) } })"
    class="group flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-subtle-ash">
    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-subtle-ash text-steel-gray transition-colors group-hover:bg-ink-black group-hover:text-white">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
          d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
      </svg>
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-sm font-medium text-ink-black" x-text="copied ? 'Copiado!' : {{ json_encode($label) }}"></p>
      <p class="truncate font-mono text-xs text-steel-gray" x-text="{{ $value }} || '—'"></p>
    </div>
  </button>
</div>
