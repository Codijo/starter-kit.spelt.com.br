@props([
    'label',                 // Texto fixo; use :labelExpr para expressão Alpine
    'labelExpr' => null,     // Expressão Alpine — vence o `label` quando presente
    'subtitle' => null,      // Texto fixo do subtítulo
    'subtitleExpr' => null,  // Expressão Alpine para o subtítulo
    'icon' => [],            // Paths de um SVG de stroke 24x24
    'tone' => 'slate',       // slate | green | blue | purple | amber | violet | red
    'action' => null,        // Expressão do @click (renderiza <button>)
    'href' => null,          // Expressão :href (renderiza <a>)
    'enabled' => null,       // Expressão Alpine; ausente = sempre habilitado
    'reason' => null,        // Expressão Alpine com o motivo de estar bloqueado
    'chevron' => false,      // Seta à direita — quando a ação leva para outra tela
])

@php
    $tones = [
        'slate' => 'bg-subtle-ash text-steel-gray group-hover:bg-ink-black group-hover:text-white',
        'green' => 'bg-green-50 text-green-600 group-hover:bg-green-600 group-hover:text-white',
        'blue' => 'bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white',
        'purple' => 'bg-purple-50 text-purple-600 group-hover:bg-purple-600 group-hover:text-white',
        'amber' => 'bg-amber-50 text-amber-600 group-hover:bg-amber-600 group-hover:text-white',
        'violet' => 'bg-violet-50 text-violet-600 group-hover:bg-violet-600 group-hover:text-white',
        'red' => 'bg-red-50 text-red-600 group-hover:bg-red-600 group-hover:text-white',
    ];
    $toneClass = $tones[$tone] ?? $tones['slate'];
    $labelClass = $tone === 'red' ? 'text-red-600' : 'text-ink-black';

    // Sem `enabled`, a ação é sempre clicável — evita :class/:disabled desnecessários.
    $hasGuard = $enabled !== null;

    // Subtítulo: o `reason` só aparece ENQUANTO a ação estiver bloqueada. Antes isto era
    // `(reason) || fallback`, o que tinha dois defeitos: o motivo é um literal, então era
    // sempre verdadeiro e vencia mesmo com a ação habilitada; e a precedência de `||`
    // engolia um fallback ternário — `(reason) || a ? b : c` vira `((reason)||a) ? b : c`,
    // que estourava ao ler propriedade de objeto nulo.
    //
    // O fallback precisa ser expressão JS válida, então o texto fixo vira literal.
    $subtitleFallback = $subtitleExpr ?: json_encode($subtitle ?? '');
    $subtitleBinding = match (true) {
        $reason && $hasGuard => "({$enabled}) ? ({$subtitleFallback}) : ({$reason})",
        (bool) $subtitleExpr => "({$subtitleExpr})",
        default => null,
    };
@endphp

{{--
  Linha de ação da coluna da direita.

  Princípio herdado do painel do Spelt, e que vale manter: **a ação nunca some por
  causa do status**. Indisponível vira desabilitada, e o subtítulo passa a dizer o
  motivo. Botão que desaparece vira chamado de "a função sumiu"; botão desabilitado
  com motivo ensina o usuário.
--}}

@if ($href)
  <a :href="{{ $href }}" class="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-subtle-ash">
@else
  <button type="button"
    @if ($action) @click="{{ $action }}" @endif
    @if ($hasGuard)
      :disabled="!({{ $enabled }})"
      :class="({{ $enabled }}) ? 'hover:bg-subtle-ash' : 'cursor-not-allowed'"
      @if ($reason) :title="({{ $enabled }}) ? {{ json_encode($label) }} : ({{ $reason }})" @endif
    @endif
    class="group flex w-full items-center gap-3 px-4 py-3 text-left transition-colors @unless ($hasGuard) hover:bg-subtle-ash @endunless">
@endif

  <div @class([
          'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-colors',
          $toneClass => !$hasGuard,
      ])
    @if ($hasGuard) :class="({{ $enabled }}) ? {{ json_encode($toneClass) }} : 'bg-subtle-ash text-steel-gray'" @endif>
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      @foreach ($icon as $path)
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $path }}" />
      @endforeach
    </svg>
  </div>

  <div class="min-w-0 flex-1">
    <p @class(['text-sm font-medium', $labelClass => !$hasGuard])
      @if ($hasGuard) :class="({{ $enabled }}) ? {{ json_encode($labelClass) }} : 'text-steel-gray'" @endif
      @if ($labelExpr) x-text="{{ $labelExpr }}" @endif>{{ $labelExpr ? '' : $label }}</p>

    @if ($subtitle || $subtitleBinding)
      <p class="text-xs text-steel-gray"
        @if ($subtitleBinding) x-text="{{ $subtitleBinding }}" @endif>{{ $subtitleBinding ? '' : $subtitle }}</p>
    @endif
  </div>

  @if ($chevron)
    <svg class="h-4 w-4 shrink-0 text-steel-gray transition-colors group-hover:text-ink-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
    </svg>
  @endif

@if ($href)
  </a>
@else
  </button>
@endif
