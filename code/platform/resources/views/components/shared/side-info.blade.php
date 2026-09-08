@props([
    'label',                // Rótulo fixo
    'value' => null,        // Expressão Alpine com o valor
    'href' => null,         // Expressão :href — transforma o valor em link
    'show' => null,         // Expressão Alpine; ausente = sempre visível
    'mono' => false,        // Valor em fonte monoespaçada (IDs, códigos)
    'inline' => false,      // Rótulo e valor na mesma linha (dados curtos)
    'empty' => '—',         // Texto quando o valor é vazio
])

{{--
  Linha do cartão "Informações": um dado, e o caminho para ele quando existir.

  `inline` para dado curto (Recorrência, Início) e o formato empilhado para o que
  precisa de espaço ou vira link (Plano, Cliente). O que decide é o comprimento do
  valor, não a importância.
--}}

@if ($inline)
  <div class="grid grid-cols-2 gap-2 px-4 py-3" @if ($show) x-show="{{ $show }}" @endif>
    <span class="text-xs text-steel-gray">{{ $label }}</span>
    @if ($href)
      <a :href="{{ $href }}" class="truncate text-right text-xs font-medium text-accent-blue hover:underline"
        x-text="{{ $value }} || {{ json_encode($empty) }}"></a>
    @else
      <span @class(['text-right text-xs font-medium text-ink-black', 'font-mono' => $mono])
        x-text="{{ $value }} || {{ json_encode($empty) }}"></span>
    @endif
  </div>
@else
  <div class="px-4 py-3" @if ($show) x-show="{{ $show }}" @endif>
    <p class="text-xs font-medium text-steel-gray">{{ $label }}</p>

    @if ($href)
      {{-- Link só quando há destino: âncora para lugar nenhum frustra o clique. --}}
      <template x-if="{{ $value }}">
        <a :href="{{ $href }}"
          @class(['mt-0.5 block truncate text-sm font-medium text-accent-blue hover:underline', 'font-mono' => $mono])
          x-text="{{ $value }}"></a>
      </template>
      <template x-if="!({{ $value }})">
        <p class="mt-0.5 text-sm text-steel-gray">{{ $empty }}</p>
      </template>
    @else
      <p @class(['mt-0.5 text-sm text-ink-black', 'break-all font-mono text-xs text-steel-gray' => $mono])
        x-text="{{ $value }} || {{ json_encode($empty) }}"></p>
    @endif

    {{ $slot }}
  </div>
@endif
