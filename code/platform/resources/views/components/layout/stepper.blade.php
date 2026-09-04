{{--
  Stepper horizontal para wizards/fluxos de várias etapas. Padrão-base do kit.
  Passo concluído = ✓ verde; passo atual = número preto; futuros = cinza.

  <x-layout.stepper :steps="['Marca','Persona','Campanha','Ganchos']" current="step" />

  steps   = array de rótulos (server-side)
  current = expressão Alpine com o passo ATUAL (1-based), avaliada no x-data da tela
--}}
@props(['steps' => [], 'current' => 'step'])

<ol class="flex items-center gap-1 text-sm">
    @foreach ($steps as $i => $label)
        <li class="flex items-center gap-1">
            <div class="flex items-center gap-2">
                <span class="grid h-6 w-6 place-items-center rounded-full text-xs font-semibold"
                    :class="({{ $current }}) > {{ $i + 1 }} ? 'bg-fresh-green text-canvas-white' : (({{ $current }}) === {{ $i + 1 }} ? 'bg-ink-black text-canvas-white' : 'bg-subtle-ash text-steel-gray')">
                    <span x-show="({{ $current }}) > {{ $i + 1 }}">✓</span>
                    <span x-show="({{ $current }}) <= {{ $i + 1 }}">{{ $i + 1 }}</span>
                </span>
                <span class="hidden font-medium sm:inline"
                    :class="({{ $current }}) === {{ $i + 1 }} ? 'text-ink-black' : 'text-linear-gray-light'">{{ $label }}</span>
            </div>
            @unless ($loop->last)
                <svg class="mx-1 h-4 w-4 shrink-0 text-border-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            @endunless
        </li>
    @endforeach
</ol>
