@props([
    'title',
    'subtitle' => null,
    // Paths de um SVG de stroke (24x24), como no menu lateral. Opcional.
    'icon' => [],
    // Use quando o corpo for conteúdo livre (um botão, um parágrafo) em vez da
    // pilha de linhas de ação. Ver a nota sobre padding abaixo.
    'padded' => false,
])

{{--
  Cartão da coluna da direita nas telas de detalhe.

  A coluna da direita é sempre a mesma sequência: Controles (o que dá para fazer),
  Informações (para onde ir) e Ações Rápidas (o que copiar/abrir). Manter os três
  no mesmo formato é o que faz o usuário aprender uma tela e saber usar as outras.

  Uso:
    <x-shared.side-card title="Controles" subtitle="Ciclo de vida desta marca." :icon="[...]">
      <x-shared.side-action ... />
    </x-shared.side-card>

  ── Sobre o padding do corpo ──────────────────────────────────────────────
  Por padrão o corpo NÃO tem padding, porque o conteúdo esperado é uma pilha de
  <x-shared.side-action>: cada uma é uma linha de ponta a ponta, com o próprio
  `px-4 py-3` e separada pelo `divide-y`. Dar padding aqui criaria uma margem
  dupla e as divisórias parariam antes da borda.

  Quando o corpo for conteúdo livre — um botão, um parágrafo — use `padded`.
  Sem ele o conteúdo encosta na borda do cartão.

    <x-shared.side-card title="Controles" padded>
      <button type="submit" class="w-full …">Salvar</button>
    </x-shared.side-card>
--}}

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-border-light bg-canvas-white shadow-subtle']) }}>
  <div class="flex items-center gap-2 border-b border-border-light px-4 py-3">
    @if (!empty($icon))
      <svg class="h-4 w-4 shrink-0 text-steel-gray" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        @foreach ($icon as $path)
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $path }}" />
        @endforeach
      </svg>
    @endif
    <div class="min-w-0">
      <h3 class="text-sm font-semibold text-ink-black">{{ $title }}</h3>
      @if ($subtitle)
        <p class="text-xs text-steel-gray">{{ $subtitle }}</p>
      @endif
    </div>
  </div>

  {{-- `header` permite um bloco antes da lista (ex.: o status atual nos Controles) --}}
  @isset($header)
    <div class="px-4 py-3">{{ $header }}</div>
  @endisset

  <div @class([
      'divide-y divide-border-light' => ! $padded,
      'space-y-2 px-4 py-3' => $padded,
      'border-t border-border-light' => isset($header),
  ])>
    {{ $slot }}
  </div>
</div>
