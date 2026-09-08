@props([
    'title',
    'subtitle' => null,
    // Paths de um SVG de stroke (24x24), como no menu lateral. Opcional.
    'icon' => [],
    // Expressão Alpine — o painel some quando ela for falsa. Serve para seção que só
    // existe quando há dado (sócios, filiais), sem encher a tela de "—".
    'show' => null,
    'padded' => true,
])

{{--
  Painel da coluna PRINCIPAL nas telas de detalhe.

  Contraparte do <x-shared.side-card>, que é da coluna da direita. Ali o corpo é uma pilha
  de ações; aqui é conteúdo — tabelas de atributos, listas, chips.

  Existe para que as seções de uma tela de detalhe tenham todas o mesmo cabeçalho e o mesmo
  respiro. A tela do Lead tem sete seções: sem isto, seriam sete markups ligeiramente
  diferentes, e a diferença apareceria na primeira manutenção.

  Uso:
    <x-shared.panel title="Atividade" :icon="[...]" show="profile.activity">
      …
    </x-shared.panel>

  `padded` está LIGADO por padrão, ao contrário do side-card: aqui o conteúdo livre é a
  regra, não a exceção. Desligue quando o corpo for uma lista com divisórias de ponta a ponta:

    <x-shared.panel title="Contatos" :padded="false">

  ⚠️ Com os dois-pontos. `padded="false"` passa a STRING "false", que é verdadeira em PHP —
  o padding continuaria ligado e o motivo não apareceria em lugar nenhum.
--}}

<div @if ($show) x-show="{{ $show }}" x-cloak @endif
    {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-border-light bg-canvas-white shadow-subtle']) }}>

    <div class="flex items-center justify-between gap-3 border-b border-border-light px-4 py-3 sm:px-5">
        <div class="flex min-w-0 items-center gap-2">
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

        @isset($actions)
            <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    <div @class(['px-4 py-4 sm:px-5' => $padded])>{{ $slot }}</div>
</div>
