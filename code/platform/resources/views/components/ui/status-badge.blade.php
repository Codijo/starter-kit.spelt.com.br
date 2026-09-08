{{-- Badge de status Ativo/Inativo. `value` é a EXPRESSÃO Alpine que resolve o status
     (ex.: "b.status", "item.status"). Verde = active; cinza = qualquer outro.

     `label` é opcional e também é EXPRESSÃO Alpine: use quando o rótulo vier do backend
     (ex.: "item.status_label"), seja por concordância — "Empresa Ativa" vs "Ativo" — seja
     porque a entidade tem mais estados que active/inactive. Sem ele, cai no padrão. --}}
@props(['value' => 'status', 'label' => null])

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
    :class="({{ $value }}) === 'active' ? 'bg-info-tint text-fresh-green' : 'bg-subtle-ash text-steel-gray'"
    x-text="{{ $label ? "($label)" : "({$value}) === 'active' ? 'Ativo' : 'Inativo'" }}"></span>
