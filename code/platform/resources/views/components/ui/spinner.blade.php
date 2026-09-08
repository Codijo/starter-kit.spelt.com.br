@props([
    'size' => 'sm',   // xs | sm | md
    'label' => null,  // texto ao lado; sem ele, só o círculo
])

{{--
  Indicador de espera. Padrão-base do kit.

    <x-ui.spinner />
    <x-ui.spinner size="xs" label="Procurando…" />

  ── Por que existe ────────────────────────────────────────────────────────

  Em rede local tudo responde antes de o olho perceber, e a espera nunca aparece durante o
  desenvolvimento. Na conexão do cliente ela aparece — e uma tela que não dá sinal de estar
  trabalhando parece uma tela travada.

  `aria-hidden` no desenho e o texto de verdade ao lado: leitor de tela anuncia a palavra,
  não o círculo.
--}}

@php
    $box = match ($size) {
        'xs' => 'h-3.5 w-3.5',
        'md' => 'h-5 w-5',
        default => 'h-4 w-4',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }} role="status">
    <svg class="{{ $box }} shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        {{-- O anel apagado dá o contorno; o arco é o que gira. Só o arco pareceria um
             fragmento solto girando no vazio. --}}
        <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
        <path class="opacity-90" fill="currentColor"
            d="M12 2a10 10 0 0 1 10 10h-3a7 7 0 0 0-7-7V2Z" />
    </svg>

    @if ($label)
        <span class="text-xs">{{ $label }}</span>
    @else
        <span class="sr-only">Carregando</span>
    @endif
</span>
