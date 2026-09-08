@props([
    // Rótulos simples (['Empresa','Área']) ou com apoio
    // ([['label' => 'Empresa', 'description' => 'Quem é você']]).
    'steps' => [],
    // Expressão Alpine com o passo ATUAL (1-based), avaliada no x-data da tela.
    'current' => 'step',
    // Expressão Alpine que recebe o número do passo. Presente, os passos JÁ CONCLUÍDOS
    // viram clicáveis — ex.: goto="goTo". Ausente, o stepper é só indicador.
    'goto' => null,
    'orientation' => 'horizontal',
])

{{--
  Stepper para wizards. Padrão-base do kit.

  Três estados: concluído (✓), atual (número em destaque) e futuro (número apagado).

    <x-layout.stepper :steps="['Empresa','Área','Produto','ICP']" current="step" goto="goTo" />
    <x-layout.stepper :steps="$steps" current="step" orientation="vertical" />

  ── Por que voltar é clicável e avançar não ───────────────────────────────

  Passo concluído já foi validado; deixar voltar para conferir é barato e evita o
  vai-e-vem de "Anterior" quatro vezes. Pular para a frente atravessaria a validação do
  passo atual — e o wizard existe justamente para conduzir a ordem.

  ── Por que a linha ────────────────────────────────────────────────────────

  Sem ela, os círculos parecem opções soltas. A linha diz que há um caminho, e a parte
  preenchida diz quanto dele já foi andado — informação que o número sozinho não dá.
--}}

@php
    $items = collect($steps)->map(fn ($step, $i) => [
        'index' => $i + 1,
        'label' => is_array($step) ? ($step['label'] ?? '') : $step,
        'description' => is_array($step) ? ($step['description'] ?? null) : null,
    ])->all();

    $total = max(1, count($items));

    // O estado de cada passo em uma expressão só, para não repetir a comparação em cada
    // atributo :class.
    $state = fn (int $i) => "(({$current}) > {$i} ? 'done' : (({$current}) === {$i} ? 'current' : 'todo'))";
    $clickable = fn (int $i) => $goto ? "({$current}) > {$i}" : 'false';
@endphp

@if ($orientation === 'vertical')
    <ol {{ $attributes->merge(['class' => 'space-y-1']) }}>
        @foreach ($items as $item)
            @php $i = $item['index']; @endphp
            <li>
                <button type="button"
                    @if ($goto) @click="{{ $clickable($i) }} && {{ $goto }}({{ $i }})" @endif
                    :disabled="!({{ $clickable($i) }})"
                    :class="{{ $state($i) }} === 'current' ? 'bg-canvas-white shadow-subtle border-border-light' : 'border-transparent'"
                    class="flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition-colors enabled:hover:bg-canvas-white/70 disabled:cursor-default">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-semibold transition-colors"
                        :class="{
                            'bg-fresh-green text-canvas-white': {{ $state($i) }} === 'done',
                            'bg-ink-black text-canvas-white': {{ $state($i) }} === 'current',
                            'bg-subtle-ash text-steel-gray': {{ $state($i) }} === 'todo',
                        }">
                        <span x-show="{{ $state($i) }} === 'done'">✓</span>
                        <span x-show="{{ $state($i) }} !== 'done'">{{ $i }}</span>
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm"
                            :class="{{ $state($i) }} === 'todo' ? 'text-linear-gray-light' : 'font-medium text-ink-black'">{{ $item['label'] }}</span>
                        @if ($item['description'])
                            <span class="block truncate text-xs text-linear-gray-light">{{ $item['description'] }}</span>
                        @endif
                    </span>
                </button>
            </li>
        @endforeach
    </ol>
@else
    <div {{ $attributes->merge(['class' => 'relative']) }}>
        {{-- A trilha e o quanto dela já foi andado. Fica atrás dos círculos. --}}
        <div class="absolute left-0 right-0 top-4 -z-0 h-0.5 bg-border-light" aria-hidden="true"></div>
        <div class="absolute left-0 top-4 -z-0 h-0.5 bg-fresh-green transition-all duration-300" aria-hidden="true"
            :style="`width: ${Math.max(0, Math.min(100, ((({{ $current }}) - 1) / {{ max(1, $total - 1) }}) * 100))}%`"></div>

        <ol class="relative flex items-start justify-between gap-2">
            @foreach ($items as $item)
                @php $i = $item['index']; @endphp
                <li class="flex min-w-0 flex-1 flex-col items-center text-center">
                    <button type="button"
                        @if ($goto) @click="{{ $clickable($i) }} && {{ $goto }}({{ $i }})" @endif
                        :disabled="!({{ $clickable($i) }})"
                        class="grid h-8 w-8 shrink-0 place-items-center rounded-full border-2 text-xs font-semibold transition-colors enabled:cursor-pointer disabled:cursor-default"
                        :class="{
                            'border-fresh-green bg-fresh-green text-canvas-white': {{ $state($i) }} === 'done',
                            'border-ink-black bg-ink-black text-canvas-white': {{ $state($i) }} === 'current',
                            'border-border-light bg-canvas-white text-linear-gray-light': {{ $state($i) }} === 'todo',
                        }">
                        <span x-show="{{ $state($i) }} === 'done'">✓</span>
                        <span x-show="{{ $state($i) }} !== 'done'">{{ $i }}</span>
                    </button>

                    <span class="mt-2 block max-w-full truncate text-xs sm:text-sm"
                        :class="{{ $state($i) }} === 'todo' ? 'text-linear-gray-light' : 'font-medium text-ink-black'">{{ $item['label'] }}</span>
                    @if ($item['description'])
                        <span class="hidden max-w-full truncate text-xs text-linear-gray-light sm:block">{{ $item['description'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
@endif
