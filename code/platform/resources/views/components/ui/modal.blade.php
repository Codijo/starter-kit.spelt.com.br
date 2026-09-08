{{--
  Modal / drawer.

  Dois layouts:
    - `floating` (padrão): caixa centralizada. Bom para confirmação e formulário curto.
    - `drawer` com `position="right"`: painel de altura total encostado na direita.
      É o formato de EDIÇÃO nas telas de detalhe — o registro continua visível ao lado
      enquanto se edita, e formulário longo rola sem empurrar a página.

  Uso (edição numa tela de show):
    <x-ui.modal title="Editar Empresa" size="50%" position="right" layout="drawer">
      @slot('trigger') <button>Editar</button> @endslot
      <form @submit.prevent="update($event)"> … </form>
    </x-ui.modal>

  O estado (`open`) é do próprio componente e o `trigger` é quem abre — quem usa não
  precisa declarar nada. Para abrir de fora, despache `open-modal` com o `modalId`.
--}}
@props([
    'title' => null,
    'buttonActionText' => 'Abrir Modal',
    'modalId' => \Illuminate\Support\Str::uuid(),
    // Herda o escopo Alpine do pai em vez de criar instância nova — usado quando o
    // formulário do modal precisa enxergar `item`/`update()` da tela.
    'xMethod' => null,
    'xParams' => [],
    'size' => 'md',
    'position' => 'center',
    'layout' => 'floating',
])

@php
  // - Se tem xMethod, NÃO cria nova instância, apenas herda do contexto pai
  // - Usar x-data apenas para o estado do modal (open/close)
  $shouldCreateNewInstance = !$xMethod;

  if ($shouldCreateNewInstance) {
      $xDataExpression = '{ open: false }';
  } else {
      // Herda do contexto pai, adiciona apenas o estado do modal
      $xDataExpression = '{ open: false }';
  }

  // ... resto das configurações de CSS iguais ...
  /*
   | Tamanho
   |--------------------------------------------------------------------------
   | Percentual (ex.: "45%") vira ESTILO calculado, não classe. O Tailwind gera CSS a
   | partir do que encontra no fonte, então `w-[45vw]` só existiria se alguém já tivesse
   | escrito exatamente isso — e um valor novo cairia no `default` em silêncio, abrindo
   | uma gaveta estreita sem ninguém entender por quê. Aconteceu com 45% e com 40%.
   |
   | O `min-width` usa `min(680px, 100vw)` para não estourar a tela do celular, que era o
   | efeito colateral do `min-w-[680px]` fixo.
   */
  $sizeStyle = null;

  if (preg_match('/^(\d{1,3})%?$/', (string) $size, $matches) === 1) {
      $percent = min(100, max(20, (int) $matches[1]));

      $sizeStyle = $layout === 'drawer'
          ? "width: {$percent}vw; min-width: min(680px, 100vw);"
          : "max-width: {$percent}vw; min-width: min(680px, 100vw);";
  }

  // Nome desconhecido FALHA. Antes caía num `default` estreito, e o erro só aparecia como
  // "a gaveta está torta" — sem pista de qual atributo causou.
  $namedSize = fn (array $map) => $sizeStyle !== null
      ? ''
      : ($map[$size] ?? throw new InvalidArgumentException(
          "x-ui.modal: tamanho \"{$size}\" desconhecido. Use um percentual (ex.: \"45%\") "
          .'ou um destes: '.implode(', ', array_keys($map)).'.'
      ));

  $floatingSizeClass = $namedSize([
      'sm' => 'max-w-sm',
      'md' => 'max-w-md',
      'xl' => 'max-w-4xl',
      '2xl' => 'max-w-6xl',
      'full' => 'max-w-full',
      'screen' => 'w-screen h-screen max-w-none',
  ]);

  $drawerSizeClass = $namedSize([
      'sm' => 'w-80',
      'md' => 'w-96',
      'xl' => 'w-[32rem]',
      '2xl' => 'w-[40rem]',
      'full' => 'w-full',
      'screen' => 'w-screen',
  ]);

  $positionWrapperClass = match ($position) {
      'right' => 'justify-end items-stretch',
      'left' => 'justify-start items-stretch',
      'top' => 'items-start justify-center',
      'bottom' => 'items-end justify-center',
      'center' => 'items-center justify-center',
      default => 'items-center justify-center',
  };

  if ($layout === 'drawer') {
      $modalContainerClass = 'h-full flex flex-col';
      $modalBoxClass = 'relative bg-canvas-white flex-1 flex flex-col shadow-2xl overflow-y-auto';
      $sizeClass = $drawerSizeClass;

      $enterAnimation = match ($position) {
          'right' => 'transform transition-transform duration-300 ease-out translate-x-full',
          'left' => 'transform transition-transform duration-300 ease-out -translate-x-full',
          default => 'transform transition-transform duration-300 ease-out translate-x-full',
      };

      $enterToAnimation = 'translate-x-0';
      $leaveAnimation = 'translate-x-0';
      $leaveToAnimation = match ($position) {
          'right' => 'translate-x-full',
          'left' => '-translate-x-full',
          default => 'translate-x-full',
      };
  } else {
      $modalContainerClass = 'relative w-full mx-4';
      $modalBoxClass = 'relative bg-canvas-white p-6 rounded-lg shadow-2xl max-h-[90vh] overflow-auto';
      $sizeClass = $floatingSizeClass;

      $enterAnimation = 'transform transition-all duration-300 ease-out opacity-0 scale-95';
      $enterToAnimation = 'opacity-100 scale-100';
      $leaveAnimation = 'opacity-100 scale-100';
      $leaveToAnimation = 'opacity-0 scale-95';
  }
@endphp

<div x-data="{!! $xDataExpression !!}"
  @open-modal.window="if ($event.detail === '{{ $modalId }}') open = true"
  @close-modal.window="if ($event.detail === '{{ $modalId }}') open = false">
  <!-- Botão/Trigger personalizado -->
  @isset($trigger)
    <div @click="open = true">
      {{ $trigger }}
    </div>
  @else
    <button @click="open = true"
      class="flex items-center justify-center rounded-lg bg-accent-blue px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-focus-ring-blue focus:outline-none focus:ring-2 focus:ring-accent-blue focus:ring-offset-2">
      {!! $buttonActionText !!}
    </button>
  @endisset

  <!-- Modal Backdrop -->
  <div
    id="{{ $modalId }}"
    x-show="open"
    @keydown.escape.window="open = false"
    @click.self="open = false"
    x-cloak
    x-transition:enter="transition-opacity duration-300 ease-out"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity duration-300 ease-in"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="{{ $positionWrapperClass }} fixed inset-0 z-50 flex bg-black bg-opacity-50 backdrop-blur-sm">

    <!-- Modal Container -->
    <div class="{{ $sizeClass }} {{ $modalContainerClass }}"
      @if ($sizeStyle) style="{{ $sizeStyle }}" @endif
      x-show="open"
      x-transition:enter="{{ $enterAnimation }}"
      x-transition:enter-start="{{ $enterAnimation }}"
      x-transition:enter-end="{{ $enterToAnimation }}"
      x-transition:leave="transition-transform duration-300 ease-in {{ $leaveAnimation }}"
      x-transition:leave-start="{{ $leaveAnimation }}"
      x-transition:leave-end="{{ $leaveToAnimation }}">

      <!-- Modal Content -->
      <div class="{{ $modalBoxClass }}">
        <!-- Header -->
        <div class="{{ $layout === 'drawer' ? 'p-6 border-b border-border-light' : 'mb-4' }} flex items-center justify-between">
          @hasSection('header')
            @yield('header')
          @elseif (isset($header))
            {{ $header }}
          @else
            <h3 class="text-lg font-semibold text-ink-black">
              {{ $title ?? $buttonActionText }}
            </h3>
          @endif

          <button
            @click="open = false"
            type="button"
            class="rounded-lg p-1.5 text-steel-gray transition-colors hover:bg-subtle-ash hover:text-ink-black focus:outline-none focus:ring-2 focus:ring-border-light">
            <span class="sr-only">Fechar modal</span>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="{{ $layout === 'drawer' ? 'flex-1 p-6 overflow-auto' : '' }}">
          @isset($body)
            {{ $body }}
          @else
            {{ $slot }}
          @endisset
        </div>

        <!-- Footer -->
        @isset($footer)
          <div class="border-t border-border-light p-4 text-right">
            {{ $footer }}
          </div>
        @endisset
      </div>
    </div>
  </div>
</div>
