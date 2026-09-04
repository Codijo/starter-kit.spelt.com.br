@props([
    'model' => null,          // Alpine lvalue da FK — ex: "form.brand_id" (obrigatório)
    'options' => '[]',        // Alpine array de opções — ex: "brands" (obrigatório)
    'nameField' => 'name',    // prop de texto exibida
    'metaField' => null,      // prop opcional na coluna mono à direita (omitido = sem coluna)
    'placeholder' => 'Buscar…',
    'emptyText' => 'Nenhum selecionado.',
    'allowClear' => true,
    'disabled' => 'false',    // expressão Alpine — ex: "!form.brand_id"
    'onChange' => '',         // statement Alpine rodado após selecionar/limpar — ex: "onBrandChange()"
])
@php
    // Todas as expressões são avaliadas no escopo Alpine do PAI (form.*, options[]),
    // exceto rsQuery/rsOpen, que vêm do x-data efêmero deste componente.
    $selected = "({$options} || []).find(o => o.id === {$model})";
    $filtered = "({$options} || []).filter(o => !rsQuery || String(o.{$nameField} || '').toLowerCase().includes(rsQuery.toLowerCase()))";
    $selMeta  = $metaField ? "(sel.{$metaField} ?? '')" : null;
    $optMeta  = $metaField ? "(rs_opt.{$metaField} ?? '')" : null;
    $doAllowClear = ($allowClear === true || $allowClear === 'true');
    $after = trim($onChange) !== '' ? '; ' . $onChange : '';
@endphp

{{--
  x-form.relation-select-one (variante CLIENT-SIDE) — seletor 1:1 (BelongsTo) sobre um
  array já carregado em memória. Mesmo visual do componente do App, sem busca assíncrona:
  filtra `options` no cliente. Não cria x-data do pai; herda `form.*`/`options[]` do escopo.

  <x-form.relation-select-one
      model="form.brand_id" options="brands"
      placeholder="Buscar marca…" empty-text="Nenhuma marca selecionada." />
--}}
<div x-data="{ rsOpen: false, rsQuery: '' }" class="relative" @click.outside="rsOpen = false">
    {{-- Item selecionado --}}
    <template x-for="sel in ([{!! $selected !!}].filter(Boolean))" :key="sel.id">
        <div class="mb-2 flex items-center gap-2 rounded-lg border border-accent-blue/30 bg-accent-blue/5 px-3 py-2">
            <span class="flex-1 truncate text-sm font-medium text-ink-black" x-text="sel.{{ $nameField }} || '—'"></span>
            @if($selMeta)
                <span class="font-mono text-xs text-steel-gray" x-text="{!! $selMeta !!}"></span>
            @endif
            @if($doAllowClear)
                <button type="button" @click="{!! $model !!} = ''{!! $after !!}" title="Remover"
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-steel-gray transition hover:bg-red-50 hover:text-red-500">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>
    </template>
    <template x-if="!({!! $selected !!})">
        <p class="mb-2 text-sm text-steel-gray">{{ $emptyText }}</p>
    </template>

    {{-- Campo de busca --}}
    <div class="flex items-center gap-2 rounded-lg border border-border-light bg-subtle-ash px-3 py-2 focus-within:border-accent-blue focus-within:bg-canvas-white focus-within:ring-1 focus-within:ring-accent-blue/20"
        :class="({!! $disabled !!}) ? 'opacity-60' : ''">
        <svg class="h-4 w-4 shrink-0 text-steel-gray" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803 7.5 7.5 0 0 0 15.803 15.803Z" />
        </svg>
        <input type="text" autocomplete="off" x-model="rsQuery"
            :disabled="{!! $disabled !!}"
            @focus="if (!({!! $disabled !!})) rsOpen = true"
            placeholder="{{ $placeholder }}"
            class="w-full border-0 bg-transparent text-sm text-ink-black placeholder-steel-gray outline-none focus:ring-0">
    </div>

    {{-- Dropdown de resultados --}}
    <div x-show="rsOpen" x-cloak
        class="absolute left-0 right-0 z-50 mt-1 max-h-64 overflow-y-auto rounded-lg border border-border-light bg-canvas-white shadow-lg">
        <template x-for="rs_opt in {!! $filtered !!}" :key="rs_opt.id">
            <button type="button"
                @mousedown.prevent="{!! $model !!} = rs_opt.id; rsQuery = ''; rsOpen = false{!! $after !!}"
                class="flex w-full items-center gap-2 px-3 py-2.5 text-left transition hover:bg-subtle-ash"
                :class="rs_opt.id === {!! $model !!} ? 'bg-accent-blue/5' : ''">
                <span class="flex-1 truncate text-sm font-medium text-ink-black" x-text="rs_opt.{{ $nameField }} || '—'"></span>
                @if($optMeta)
                    <span class="shrink-0 font-mono text-xs text-steel-gray" x-text="{!! $optMeta !!}"></span>
                @endif
            </button>
        </template>
        <template x-if="({!! $filtered !!}).length === 0">
            <p class="px-3 py-2.5 text-sm text-steel-gray">Nenhum resultado.</p>
        </template>
    </div>
</div>
