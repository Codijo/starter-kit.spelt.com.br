{{--
  Avatar-monograma: quadrado arredondado com a inicial do nome. Para células de nome em
  tabelas/listas quando não há foto. `name` = expressão Alpine (ex: "b.name").

  <x-ui.avatar name="b.name" />
--}}
@props(['name' => "''"])

<span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-subtle-ash text-sm font-semibold text-steel-gray"
    x-text="(String(({{ $name }}) ?? '').trim().charAt(0) || '?').toUpperCase()"></span>
