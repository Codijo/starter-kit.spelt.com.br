@props([
    'items'         => '[]',
    'searchModel'   => 'q',
    'results'       => '[]',
    'searching'     => 'false',
    'open'          => 'false',
    'onInput'       => 'onInput',
    'onAttach'      => 'onAttach',
    'onDetach'      => 'onDetach',
    'onOpen'        => '',
    'fetchOnFocus'  => false,
    'nameField'     => 'name',
    'photoField'    => 'profile_photo',
    // Ícone do chip quando não há foto. Default: pessoa (o caso original, relação com
    // gente). Passe outro path quando a relação não for de pessoas — tag, produto, etc.
    'icon'          => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
    'placeholder'   => 'Buscar…',
    'emptyText'     => 'Nenhum item. Use a busca abaixo para adicionar.',
])
@php $doFetchOnFocus = ($fetchOnFocus === true || $fetchOnFocus === 'true') && !empty($onOpen); @endphp

{{--
╔══════════════════════════════════════════════════════════════════════════════╗
║  x-form.relation-select  —  Seletor N:N com busca e chips                  ║
╚══════════════════════════════════════════════════════════════════════════════╝

Gerencia uma relação muitos-para-muitos de forma inline: exibe os itens já
vinculados como chips removíveis + campo de busca com dropdown para adicionar
novos itens, sem recarregar a página.

Funciona para qualquer relação N:N: recurso → tags, recurso → categorias,
recurso → pessoas, recurso → produtos, etc.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PRINCÍPIO DE FUNCIONAMENTO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

O componente NÃO cria x-data próprio. Ele opera diretamente no escopo Alpine
do componente pai (ex: Alpine.data('post', ...), Alpine.data('product', ...)).
Todas as props são injetadas como expressões Alpine via {!! $prop !!} — o Blade
as interpola antes do Alpine avaliar. Por isso as props recebem NOMES de
variáveis e métodos Alpine, não valores concretos.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
USO NO BLADE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Substitua {rel} pelo nome da entidade relacionada no seu contexto (ex: tag,
author, category). Os nomes de variáveis e métodos Alpine devem ser únicos
dentro do componente pai para evitar conflito quando houver múltiplos
relation-select na mesma página.

    <x-form.relation-select
        items="item.{rel}s"
        search-model="{rel}Search"
        results="{rel}Results"
        searching="{rel}Searching"
        open="{rel}SearchOpen"
        on-input="on{Rel}SearchInput"
        on-attach="attach{Rel}"
        on-detach="detach{Rel}"
        name-field="name"
        photo-field="profile_photo"
        placeholder="Buscar {rel} pelo nome…"
        empty-text="Nenhum {rel} vinculado. Use a busca abaixo para adicionar."
    />

Props:
    items          Alpine expression → array dos itens já vinculados
    search-model   Alpine variable   → valor do input de busca (string)
    results        Alpine expression → array dos resultados da busca
    searching      Alpine expression → bool, exibe spinner enquanto true
    open           Alpine expression → bool, exibe o dropdown enquanto true
    on-input       Alpine method     → chamado com (value) a cada keystroke
    on-attach      Alpine method     → chamado com (item) ao selecionar resultado
    on-detach      Alpine method     → chamado com (item) ao clicar no × do chip
    fetch-on-focus bool (default: false) → ao focar o input vazio, chama on-open() sem aguardar digitação
    on-open        Alpine method     → chamado sem args quando o input é focado vazio (requer fetch-on-focus)
    name-field     JS property       → campo de texto exibido no chip/resultado (default: name)
    photo-field    JS property       → campo do objeto foto com subprop .path   (default: profile_photo)
    placeholder    string            → placeholder do input
    empty-text     string            → texto exibido quando não há itens vinculados

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CONTRATO JS — o que o componente pai DEVE ter no Alpine data
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Adicione ao objeto Alpine do pai (ex: dentro de Alpine.data('myComponent', () => ({

Substitua {rel} pelo nome da entidade relacionada (ex: tag, author, category)
e {Rel} pela versão com inicial maiúscula (ex: Tag, Author, Category).
Substitua {rels} pelo plural (ex: tags, authors, categories).
Substitua /api/backoffice/{rels} pela URL do endpoint de busca da entidade.
Substitua /{rels} pelo segmento de rota da pivot no controller pai.
Substitua {rel}_id pelo nome do campo esperado pelo endpoint de attach.

    // ── Estado (nomes correspondem às props do componente) ────────────────
    {rel}Search:        '',     // → prop search-model
    {rel}Results:       [],     // → prop results
    {rel}Searching:     false,  // → prop searching
    {rel}SearchOpen:    false,  // → prop open
    _{rel}SearchTimeout: null,  // interno, não exposto ao componente

    // ── Método: on-input ──────────────────────────────────────────────────
    // Regras obrigatórias:
    //   • Deve ter debounce interno (não confiar no Alpine @input.debounce,
    //     pois o componente chama o método via @input normal).
    //   • Quando chamado com value vazio (''), deve limpar {rel}Results,
    //     fechar {rel}SearchOpen e zerar {rel}Search. O Escape do teclado
    //     chama este método com '' para fazer o reset completo do estado.
    //   • Quando value.length < 2, apenas limpa sem chamar a API.
    on{Rel}SearchInput(value) {
      this.{rel}Search = value;
      clearTimeout(this._{rel}SearchTimeout);
      if (!value || value.length < 2) {
        this.{rel}Results = [];
        this.{rel}SearchOpen = false;
        return;
      }
      this._{rel}SearchTimeout = setTimeout(() => this.search{Rel}s(value), 320);
    },

    // ── Método: busca na API ──────────────────────────────────────────────
    // Filtra da resposta os itens já vinculados em item.{rels} para que
    // não apareçam como opção no dropdown.
    async search{Rel}s(q) {
      this.{rel}Searching = true;
      try {
        const url = this.apiUrl + '/api/backoffice/{rels}?per_page=12&name=' + encodeURIComponent(q);
        const response = await Api.getResource(url);
        const results = response.data?.data || [];
        const attachedIds = new Set((this.item.{rels} || []).map(r => r.id));
        this.{rel}Results = results.filter(r => !attachedIds.has(r.id));
        this.{rel}SearchOpen = this.{rel}Results.length > 0;
      } catch (_) {
        this.{rel}Results = [];
        this.{rel}SearchOpen = false;
      } finally {
        this.{rel}Searching = false;
      }
    },

    // ── Método: on-open (opcional, usado com fetch-on-focus="true") ───────────
    // Chamado sem args quando o usuário foca o input vazio.
    // DEVE: disparar a busca sem filtro para exibir os primeiros N itens disponíveis.
    // A API já lida com query vazia — o controller usa filled() antes do where like,
    // então GET /api/.../activity?per_page=12 retorna os primeiros N ordenados DESC.
    on{Rel}Open() {
      this.search{Rel}s('');
    },

    // ── Método: on-attach ─────────────────────────────────────────────────
    // Regras obrigatórias:
    //   • Após sucesso, atualizar item.{rels} com a lista retornada pela API.
    //   • Remover o item de {rel}Results para sumir do dropdown imediatamente.
    //   • Fechar {rel}SearchOpen se o dropdown ficar vazio.
    //   • NÃO precisa zerar {rel}Search — o x-model cuida disso quando
    //     o servidor retorna e o componente re-renderiza.
    async attach{Rel}(entry) {
      try {
        const url = this.baseApiUrl() + '/' + this.id + '/{rels}';
        const response = await Api.storeResource(url, { {rel}_id: entry.id });
        this.item = { ...this.item, {rels}: response.data };
        this.{rel}Results = this.{rel}Results.filter(r => r.id !== entry.id);
        if (!this.{rel}Results.length) this.{rel}SearchOpen = false;
        Alpine.store('flash').success(entry.name + ' adicionado(a).');
      } catch (error) {
        Alpine.store('flash').error(window.handleApiError?.().format(error, 'ul') || error.message);
      }
    },

    // ── Método: on-detach ─────────────────────────────────────────────────
    // Regra obrigatória:
    //   • Após sucesso, atualizar item.{rels} com a lista retornada pela API.
    async detach{Rel}(entry) {
      if (!confirm('Remover ' + entry.name + '?')) return;
      try {
        const url = this.baseApiUrl() + '/' + this.id + '/{rels}/' + entry.id;
        const response = await Api.deleteResource(url);
        this.item = { ...this.item, {rels}: response.data };
        Alpine.store('flash').success(entry.name + ' removido(a).');
      } catch (error) {
        Alpine.store('flash').error(window.handleApiError?.().format(error, 'ul') || error.message);
      }
    },

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CONTRATO API — endpoints que o back-end DEVE expor
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Nos exemplos abaixo: {parent} = recurso pai (ex: post, product, video),
{rels} = relação plural (ex: tags, authors, actresses).

1. BUSCA — endpoint já existente da entidade relacionada, sem modificações.
   GET  /api/backoffice/{rels}?name={q}&per_page=12
   Resposta: { data: { data: [ { id, name, photo_field: { path } } ] } }
   Requisito: o index() da entidade deve eager-load a foto (ex: ->with('profilePhoto')).

2. ATTACH — vincula um item à pivot (idempotente, não duplica).
   POST /api/backoffice/{parent}/{id}/{rels}
   Body:     { "{rel}_id": "ULID" }
   Resposta: { data: [ ...lista atualizada com foto eager-loaded... ] }
   Laravel:  $model->{rels}()->syncWithoutDetaching([$request->{rel}_id]);
             return response()->json(['data' => $model->{rels}()->with('profilePhoto')->get()]);

3. DETACH — desvincula um item da pivot.
   DELETE /api/backoffice/{parent}/{id}/{rels}/{relId}
   Resposta: { data: [ ...lista atualizada com foto eager-loaded... ] }
   Laravel:  $model->{rels}()->detach($relId);
             return response()->json(['data' => $model->{rels}()->with('profilePhoto')->get()]);

── Arquivo de rotas ({Parent}Route.php) ──────────────────────────────────────

    ->where([
        'id'    => '(?i)[0123456789ABCDEFGHJKMNPQRSTVWXYZ]{26}',
        'relId' => '(?i)[0123456789ABCDEFGHJKMNPQRSTVWXYZ]{26}', // ← adicionar
    ])

    Route::post('/{id}/{rels}',          [{Parent}Controller::class, 'attach{Rel}']);
    Route::delete('/{id}/{rels}/{relId}', [{Parent}Controller::class, 'detach{Rel}']);

── show() do controller pai ──────────────────────────────────────────────────

    // Adicionar dentro do with([...]) existente:
    '{rels}' => fn ($q) => $q->with('profilePhoto'),

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
IMPLEMENTAÇÃO DE REFERÊNCIA (implementação real para consulta)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Relação: Video → Actress (pivot: content_actress_video)

  Blade:      resources/views/web/pages_guard/content/video/show.blade.php
  JS:         resources/views/web/pages_guard/content/video/base_video.js
  Controller: app/Http/Controllers/Backoffice/Content/VideoController.php
  Rotas:      routes/api/Backoffice/Content/VideoRoute.php

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NOTAS TÉCNICAS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- Variáveis de loop usam prefixo rel_ (rel_chip, rel_result) para não colidir
  com variáveis do escopo pai (ex: a variável 'item' do recurso em edição).

- @mousedown.prevent no botão do dropdown impede que o input perca o foco
  antes do click ser processado — sem isso, @blur fecha o dropdown antes
  do item ser selecionado.

- @blur usa setTimeout de 200ms pelo mesmo motivo: garante que o evento
  mousedown/click complete antes de fechar o dropdown.

- Pressionar Escape chama onInput('') que, por contrato, reseta todo o estado
  (search, results, open) sem precisar de lógica extra no template.

- {!! $prop !!} em vez de {{ $prop }}: as props são expressões Alpine como
  "item.tags" ou "attachTag", e {{ }} escaparia colchetes e aspas quebrando
  os bindings. Use {!! !!} para todas as expressões injetadas no Alpine.
--}}

{{-- ── Chips dos itens selecionados ──────────────────────────────────────── --}}
<div class="mb-4">
    <p x-show="!({!! $items !!})?.length" class="text-sm text-steel-gray">
        {{ $emptyText }}
    </p>

    <div x-show="({!! $items !!})?.length > 0" class="flex flex-wrap gap-2">
        <template x-for="rel_chip in ({!! $items !!} || [])" :key="rel_chip.id">
            <div class="flex items-center gap-2 rounded-full border border-border-light bg-subtle-ash py-1 pl-1 pr-3 shadow-sm">

                <template x-if="rel_chip.{{ $photoField }}?.path">
                    <img :src="rel_chip.{{ $photoField }}.path"
                        :alt="rel_chip.{{ $nameField }}"
                        class="h-7 w-7 rounded-full object-cover ring-1 ring-border-light">
                </template>
                <template x-if="!rel_chip.{{ $photoField }}?.path">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-canvas-white text-steel-gray ring-1 ring-border-light">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $icon }}" />
                        </svg>
                    </div>
                </template>

                <span class="text-sm font-medium text-ink-black"
                    x-text="rel_chip.{{ $nameField }}"></span>

                <button type="button"
                    @click="{!! $onDetach !!}(rel_chip)"
                    :title="'Remover ' + rel_chip.{{ $nameField }}"
                    class="flex h-5 w-5 items-center justify-center rounded-full text-steel-gray transition hover:bg-border-light hover:text-ink-black">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

            </div>
        </template>
    </div>
</div>

{{-- ── Campo de busca ──────────────────────────────────────────────────────── --}}
<div class="relative">
    <div class="flex items-center gap-2 rounded-lg border border-border-light bg-subtle-ash px-3 py-2 focus-within:border-accent-blue focus-within:bg-canvas-white focus-within:ring-1 focus-within:ring-accent-blue/20">
        <svg class="h-4 w-4 shrink-0 text-steel-gray" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803 7.5 7.5 0 0015.803 15.803z" />
        </svg>
        <input type="text"
            autocomplete="off"
            x-model="{!! $searchModel !!}"
            @input="{!! $onInput !!}($event.target.value)"
            @keydown.escape="{!! $onInput !!}('')"
            @blur="setTimeout(() => { {!! $open !!} = false }, 200)"
            @if($doFetchOnFocus)@focus="if (!{!! $searchModel !!}) {!! $onOpen !!}()"
            @endif
            placeholder="{{ $placeholder }}"
            class="w-full border-0 bg-transparent text-sm text-ink-black placeholder-steel-gray outline-none focus:outline-none focus:ring-0">
        <svg x-show="{!! $searching !!}"
            class="h-4 w-4 shrink-0 animate-spin text-steel-gray" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>

    {{-- Dropdown de resultados --}}
    <div x-show="{!! $open !!} && ({!! $results !!})?.length > 0"
        class="absolute left-0 right-0 top-full z-50 mt-1 max-h-64 overflow-y-auto rounded-lg border border-border-light bg-canvas-white shadow-lg">
        <template x-for="rel_result in ({!! $results !!} || [])" :key="rel_result.id">
            <button type="button"
                @mousedown.prevent="{!! $onAttach !!}(rel_result)"
                class="flex w-full items-center gap-3 px-3 py-2.5 text-left transition hover:bg-subtle-ash">

                <template x-if="rel_result.{{ $photoField }}?.path">
                    <img :src="rel_result.{{ $photoField }}.path"
                        :alt="rel_result.{{ $nameField }}"
                        class="h-8 w-8 shrink-0 rounded-full object-cover ring-1 ring-border-light">
                </template>
                <template x-if="!rel_result.{{ $photoField }}?.path">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-subtle-ash text-steel-gray ring-1 ring-border-light">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $icon }}" />
                        </svg>
                    </div>
                </template>

                <span class="flex-1 truncate text-sm font-medium text-ink-black"
                    x-text="rel_result.{{ $nameField }}"></span>

                <svg class="h-4 w-4 shrink-0 text-accent-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </button>
        </template>
    </div>
</div>
