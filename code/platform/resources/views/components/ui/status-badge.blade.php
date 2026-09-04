{{-- Badge de status Ativo/Inativo. `value` é a EXPRESSÃO Alpine que resolve o status
     (ex.: "b.status", "item.status"). Verde = active; cinza = qualquer outro. --}}
@props(['value' => 'status'])

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
    :class="({{ $value }}) === 'active' ? 'bg-info-tint text-fresh-green' : 'bg-subtle-ash text-steel-gray'"
    x-text="({{ $value }}) === 'active' ? 'Ativo' : 'Inativo'"></span>
