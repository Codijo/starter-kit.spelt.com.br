@props([
    // Nome do que está listado, para a frase do rodapé: "1-10 de 90 contatos".
    'label' => 'registros',
])

{{--
    Rodapé de paginação de listagem.

    ── Como se instala ───────────────────────────────────────────────────────

    Espalhe `...window.paginated(),` no componente Alpine da tela e coloque este componente
    depois da tabela. Não há prop de dado nem evento a ligar: ele lê `meta`, `pageNumbers()`
    e `goToPage()` do escopo da própria tela.

    O `load()` da tela precisa mandar `page: this.page` para a API e guardar a resposta em
    `this.meta`. Filtro e busca chamam `reload()`, não `load()` — ver a nota no mixin.

    ── Por que números, e não "carregar mais" ────────────────────────────────

    "Carregar mais" só anda para frente: quem viu algo na página 4 e mudou de tela precisa
    percorrer tudo de novo. E a URL nunca aponta para onde a pessoa está.

    ── Por que a frase aparece mesmo com uma página só ───────────────────────

    "Mostrando 1-7 de 7" é a confirmação de que a lista está inteira ali. Sem ela, uma lista
    curta é indistinguível de uma lista cortada.
--}}
<div x-show="(meta.total ?? 0) > 0" x-cloak
    {{ $attributes->merge(['class' => 'flex flex-col items-start justify-between gap-3 border-t border-border-light bg-subtle-ash/40 px-4 py-3 md:flex-row md:items-center']) }}>

    <span class="text-xs text-linear-gray-dark">
        Mostrando
        <span class="font-semibold text-steel-gray" x-text="`${meta.from ?? 0}-${meta.to ?? 0}`"></span>
        de
        <span class="font-semibold text-steel-gray" x-text="meta.total ?? 0"></span>
        {{ $label }}
    </span>

    <nav x-show="(meta.last_page ?? 1) > 1" aria-label="Paginação">
        <ul class="inline-flex items-stretch -space-x-px">
            <li>
                <button type="button" @click="goToPage(meta.current_page - 1)"
                    :disabled="(meta.current_page ?? 1) <= 1"
                    aria-label="Página anterior"
                    class="flex items-center justify-center rounded-l-lg border border-border-light bg-canvas-white px-3 py-2 text-sm leading-tight text-steel-gray hover:bg-subtle-ash hover:text-ink-black disabled:cursor-default disabled:opacity-40 disabled:hover:bg-canvas-white">‹</button>
            </li>

            {{-- Chave pelo índice: a lista tem `null` nas reticências, que não serve de chave
                 e pode repetir (há duas num intervalo largo). --}}
            <template x-for="(page, index) in pageNumbers()" :key="index">
                <li>
                    <button type="button" @click="goToPage(page)"
                        :disabled="page === null || page === meta.current_page"
                        :aria-current="page === meta.current_page ? 'page' : null"
                        :class="page === null
                            ? 'cursor-default border-border-light bg-canvas-white text-linear-gray-light'
                            : (page === meta.current_page
                                ? 'z-10 border-accent-blue bg-canvas-white font-semibold text-accent-blue'
                                : 'border-border-light bg-canvas-white text-steel-gray hover:bg-subtle-ash hover:text-ink-black')"
                        class="flex items-center justify-center border px-3 py-2 text-sm leading-tight"
                        x-text="page ?? '…'"></button>
                </li>
            </template>

            <li>
                <button type="button" @click="goToPage(meta.current_page + 1)"
                    :disabled="(meta.current_page ?? 1) >= (meta.last_page ?? 1)"
                    aria-label="Próxima página"
                    class="flex items-center justify-center rounded-r-lg border border-border-light bg-canvas-white px-3 py-2 text-sm leading-tight text-steel-gray hover:bg-subtle-ash hover:text-ink-black disabled:cursor-default disabled:opacity-40 disabled:hover:bg-canvas-white">›</button>
            </li>
        </ul>
    </nav>
</div>
