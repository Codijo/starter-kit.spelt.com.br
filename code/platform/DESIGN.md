# DESIGN.md — convenções de tela da Platform

> **Para que serve:** este documento responde "como se escreve uma tela neste projeto".
> Leia antes de criar a primeira tela do seu produto.
> **De onde veio:** destilado das implementações vivas — `app.spelt.com.br` (o App),
> `marketing.iporto.com.br` (o mais completo) e `platform.trypsst.com` (o Psst).
> **Escrito em:** 2026-09-04, a partir dos erros cometidos na construção da Fábrica de Lead,
> o 2º produto sobre o kit.

---

## 0. Por que este arquivo existe

A spec do kit documenta exaustivamente a **costura com o Spelt** — SSO, webhook, créditos,
entitlements, provisionamento. Quem segue a spec acerta essa parte de primeira.

Sobre **como se escreve uma tela**, o kit dizia apenas "padrão App" — sem dizer onde "App"
está nem o que o padrão contém. O resultado, na Fábrica de Lead: nove convenções erradas na
primeira tela, todas descobertas por revisão manual, e um retrabalho completo.

As convenções abaixo já existiam. O que faltava era estarem escritas.

---

## 1. Idioma: código em inglês, interface em português

| Camada | Idioma |
|---|---|
| Model, coluna, namespace | inglês (`Company`, `trade_name`, `App\Models\Registry`) |
| **URL** (web e API) | **inglês** (`/companies`, `/api/registry/companies`) |
| Nome de rota | inglês (`registry.companies.create`) |
| Texto na tela | **português** ("Empresas", "Nome fantasia") |
| Comentário no código | **português** |

❌ `/empresas` · ❌ `Route::get('/empresa')` · ✅ `/companies`

**Recursos de API no plural**, seguindo o Psst (`/api/brands`, `/api/personas`) e a maioria
dos endpoints do ecossistema. Constraint de ULID no parâmetro:

```php
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';
Route::get('/companies/{company}', [CompanyController::class, 'show'])->where('company', $ulid);
```

Mantenha um **glossário PT↔EN** no `CLAUDE.md` da API. Sem ele, cada tela nova recomeça a
tradução e os nomes divergem.

---

## 2. Anatomia de uma tela: três páginas, nunca um modal que faz tudo

```
resources/views/web/<dominio>/<recursos>/
├── index.blade.php     + index.js      listagem
├── create.blade.php    + create.js     criação (página inteira)
├── show.blade.php      + show.js       detalhe + edição em drawer
├── _fields.blade.php                   campos compartilhados (quando fizer sentido)
└── show_item_modal.blade.php           o drawer de edição do show
```

Rotas em `routes/web/<Dominio>.php` (auto-carregado pelo `RouteServiceProvider`):

```php
Route::middleware('auth.token')->group(function () {
    Route::get('/companies',        [CompanyController::class, 'index'])->name('registry.companies');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('registry.companies.create');
    Route::get('/companies/{id}',   [CompanyController::class, 'show'])->name('registry.companies.show');
});
```

O controller da Platform **só devolve a view**. Nenhuma regra de negócio, nenhuma consulta:
os dados vêm da API do produto por axios. `show($id)` repassa o id para o blade; quem busca
e valida a posse é a API.

---

## 3. ⚠️ Onde o `@vite` da tela vai — o erro mais caro

**No rodapé do `@section('content')`. NUNCA em `@push('scripts')`.**

```blade
@section('content')
    <div x-data="companiesIndex()" x-init="load()">…</div>

    @vite('resources/views/web/registry/companies/index.js')
@endsection
```

**Por quê:** o `layouts/guard.blade.php` renderiza `@vite('resources/js/app.js')` e **depois**
`@stack('scripts')`. É o `app.js` que chama `Alpine.start()`. Um `@vite` empilhado na stack
carrega *depois* do start — o `alpine:init` do componente nunca dispara e a tela morre com
`companiesIndex is not defined` em cascata, um erro por diretiva.

O `@section('content')` é renderizado antes do `app.js`, então o registro chega a tempo.

### O componente da tela

Função nomeada + registro no `alpine:init`. **Não** registre componentes de página no
`modules/alpine.js` — ele é só para stores globais.

```js
function companiesIndex() {
    return {
        items: [], loading: true,
        async load() { … },
    };
}

document.addEventListener('alpine:init', () => window.Alpine.data('companiesIndex', companiesIndex));
```

Use `window.axios` (o módulo já configurou baseURL, token e o 401 → `/logout`).

---

## 4. Tela de detalhe (`show`): duas colunas

**À esquerda o que o registro É. À direita o que dá para FAZER.** Este é o layout; não
invente outro.

```blade
<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">   {{-- conteúdo --}}
    <div class="space-y-4">        {{-- controles --}}
```

### Esquerda — seções colapsáveis

Cabeçalho (ícone + nome + badge de status) e depois seções em `x-data="{ open: true }"` +
`x-collapse`. A primeira aberta, as demais fechadas. Duplique o bloco para adicionar seções.

Rodapé com datas de criação/atualização e o botão "Voltar". **"Excluir" não fica aqui** —
vive só nos Controles, onde passa por confirmação.

### Direita — sempre estes três cartões, nesta ordem

| Cartão | Papel |
|---|---|
| **Controles** | O que dá para fazer: ativar, desativar, excluir. Com o status atual no `header`. |
| **Informações** | O que o registro é, em forma de lista: datas, ID, campos-chave. |
| **Ações Rápidas** | O que copiar ou para onde ir: copiar ID, voltar à listagem. |

Manter os três iguais em toda tela é o que faz o usuário aprender uma e saber usar as outras.

```blade
<x-shared.side-card title="Controles" subtitle="Estado desta empresa." :icon="[...]">
    <x-slot:header>{{-- status atual --}}</x-slot:header>

    <x-shared.side-action label="Ativar" tone="green"
        action="updateStatus('active')"
        enabled="item.status !== 'active'"
        reason="item.status === 'active' ? 'A empresa já está ativa.' : ''"
        subtitle="Volta a contar para o limite do plano" :icon="[...]" />
</x-shared.side-card>
```

**A ação nunca some por causa do status.** Indisponível vira desabilitada, e o `reason`
explica o motivo no subtítulo. Botão que desaparece vira chamado de "a função sumiu"; botão
desabilitado com motivo ensina o usuário.

---

## 5. Edição: drawer à direita, altura total

```blade
<x-ui.modal title="Editar Empresa" size="50%" position="right" layout="drawer" xMethod="companiesShow">
    @slot('trigger') <button>Editar</button> @endslot
    <form @submit.prevent="update($event); open = false"> … </form>
</x-ui.modal>
```

O registro continua visível ao lado enquanto se edita, e formulário longo rola sem empurrar
a página. `xMethod` faz o modal herdar o escopo Alpine da tela em vez de criar um novo.

**Campos com `name=` e `:value="item.x"`, lidos por FormData:**

```js
async update(event) {
    const payload = Object.fromEntries(new FormData(event.target).entries());
    …
}
```

Sem objeto `form` espelhando `item`, não há estado para sincronizar nem para sair de sincronia.

---

## 6. Erros: 422 no campo, 400 no toast

| Status | Significado | Onde aparece |
|---|---|---|
| **422** | Validação de campo | `fieldErrors` ao lado do input + `$store.flash.validation(errs)` |
| **400** | Regra de negócio (teto do plano, estado inválido) | `$store.flash.error(message)` |
| **404** | Não encontrado | toast + redireciona para a listagem |

```js
catch (e) {
    if (e.response?.status === 422) {
        const errs = e.response.data.errors || {};
        this.fieldErrors = Object.fromEntries(Object.entries(errs).map(([k, m]) => [k, m[0]]));
        this.$store.flash.validation(errs);
    } else {
        this.$store.flash.error(e.response?.data?.message || 'Não foi possível salvar.');
    }
}
```

**A mensagem de negócio vem pronta da API** — com o que o usuário deve fazer a respeito. O
front só exibe. Regra de negócio não se duplica no JS: se ela mudar, a tela mente.

---

## 7. Limite de plano na interface

A API devolve o consumo junto da listagem; o front **não calcula teto**:

```json
{ "data": { "items": [...], "limit": { "used": 3, "limit": 3 } } }
```

Com isso a tela mostra "3 de 3 empresas", esconde o botão de criar e explica o bloqueio com
um caminho de saída (desativar ou `$store.me.managePortal()`). Calcular no front garante
divergência entre o que a tela permite e o que o backend aceita.

---

## 8. Menu lateral: acordeão dirigido por dados

`partials/sidebar.blade.php` monta a nav a partir de um array `$sections`. **Declare a
navegação inteira desde já**: item cuja rota ainda não existe é ignorado, então o menu cresce
sozinho conforme as telas são entregues.

```php
'registry' => [
    'label' => 'Cadastro',
    'hub' => 'registry.companies',        // o clique no nome da seção
    'icon' => ['M3.75 21h16.5…'],
    'children' => [
        ['route' => 'registry.companies', 'label' => 'Empresas',
         'pattern' => 'registry.companies*', 'group' => 'Estrutura'],
    ],
],
```

Uma seção aberta por vez; a da tela atual já vem aberta no primeiro paint (via `style`
server-side, **sem `x-cloak`** — ele esconderia a seção ativa até o Alpine bootar, que é
justamente o flash a evitar). `group` cria sub-cabeçalho não clicável.

---

## 9. Listagem: dez por página, sempre

Toda lista pagina, e o padrão é **10**. Não é opinião por tela — é decisão de produto, e vive
em `App\Support\Pagination` na API:

```php
->paginate(Pagination::perPage($request));   // 10, com teto de 100
// ...
'meta' => Pagination::meta($paginator),      // current_page, last_page, per_page, total, from, to
```

Dez é o número que torna a paginação **visível**. Com 50, quase toda lista caberia numa
página só, o controle nunca apareceria, e o usuário passaria a acreditar que a lista está
inteira na tela.

Na tela são três passos:

```js
function coisasIndex() {
    return {
        ...window.paginated(),        // 1. meta, page, goToPage(), pageNumbers(), reload()

        items: [], loading: true, search: '',

        async load() {
            const { data } = await window.axios.get('/api/coisas', {
                params: { page: this.page, search: this.search || undefined },   // 2. manda a página
            });
            this.items = data.data.items;
            this.meta = data.data.meta;                                          // 3. guarda o meta
        },
    };
}
```

```blade
<div x-cloak class="mt-6 overflow-hidden rounded-lg border border-border-light bg-canvas-white shadow-subtle">
    <div class="overflow-x-auto">
        <table>…</table>
    </div>

    <x-shared.pagination label="coisas" />
</div>
```

A moldura é `overflow-hidden` e só a **tabela** rola na horizontal. Com o rodapé dentro de um
`overflow-x-auto`, ele desliza junto e os números somem da vista em tabela larga.

O componente não recebe dado por prop nem dispara evento: lê `meta`, `pageNumbers()` e
`goToPage()` do escopo da tela. Espalhar o mixin **é** a instalação — e há teste de contrato
conferindo isso, além de mandar a página e guardar o `meta`.

Quando a lista paginada não é a carga principal da tela (um histórico dentro de um `show`,
por exemplo), diga qual método recarregar: `...window.paginated('loadDeliveries'),`.

> ⚠️ **Filtro, busca e ordenação chamam `reload()`, nunca `load()`.** Quem está na página 3 e
> muda o filtro veria "nenhum resultado" — porque a página 3 do novo recorte não existe — e
> concluiria que o filtro não encontra nada. `reload()` volta para a primeira página.

> ⚠️ **Select de filtro não se alimenta de endpoint paginado.** Uma tela que monta o seletor
> de Empresa chamando `/api/registry/companies` passa a oferecer dez empresas, calada. Devolva
> as opções dentro da própria resposta da listagem (`'filters' => ['companies' => …]`), como
> lista completa — opção de filtro é lista curta por natureza.

---

## 10. Use o que já existe

Antes de escrever markup, confira se há componente:

| Componente | Para |
|---|---|
| `x-layout.breadcrumb` | trilha; aceita item dinâmico via `xLabel` |
| `x-layout.page-header` | cabeçalho com slots `icon`/`badges`/`meta`/`actions` — **use para título dinâmico** |
| `x-layout.title` | título estático (props Blade **não** aceitam binding Alpine) |
| `x-layout.section-card` | seção colapsável |
| `x-ui.modal` | modal e drawer |
| `x-ui.status-badge` | badge de status; `label` opcional quando "Ativo/Inativo" não serve |
| `x-shared.side-card` / `side-action` / `side-info` / `side-copy` | a coluna da direita |
| `x-shared.pagination` | rodapé de listagem: "41-50 de 90" + números — ver §9 |
| `x-shared.export-button` | botão "Exportar" com os filtros da tela; a tela expõe `exportFilters()` |
| `x-form.relation-select` | vínculo N:N com busca e chips (tags, itens de catálogo) |
| `x-form.relation-select-one` | vínculo 1:1 com busca (FK no próprio registro) |

> Os dois `relation-select` **não criam `x-data` próprio**: operam no escopo do componente
> da tela e recebem NOMES de variáveis e métodos Alpine como props. Quando a mesma tela usa
> mais de um, os nomes precisam ser únicos. Ver a documentação no topo de cada componente.

E os globais:

- **`$store.flash`** — `success` / `error` / `validation(errs)`.
- **`$store.confirm`** — `open({title, message, confirmText, danger, onConfirm})`. **Nunca
  `window.confirm()`.** Ação destrutiva SEMPRE passa por aqui.
- **`$store.me`** — identidade, plano, entitlements, saldo, `managePortal(target)`.
- **`x-mask`** (plugin registrado) — máscara de input: `x-mask="99.999.999/9999-99"`.
- **`copyText`**, **`formatDate`**, **`formatDateTime`**, **`formatPhone`**
  (`js/modules/helpers.js`) — globais de propósito: os componentes recebem expressões como
  string, avaliadas no escopo do Alpine.
- **`paginated()`** e **`pageWindow()`** (mesmo arquivo) — a paginação da §9.

---

## 11. Checklist da primeira tela

- [ ] URL e nome de rota em inglês, recurso no plural
- [ ] Rota em `routes/web/<Dominio>.php`, controller só devolve view
- [ ] `index` / `create` / `show` — não um modal fazendo tudo
- [ ] `@vite` no rodapé do `@section('content')`, **não** em `@push('scripts')`
- [ ] Componente como função + `alpine:init` → `window.Alpine.data(...)`
- [ ] `show` em duas colunas, com Controles / Informações / Ações Rápidas
- [ ] Edição em drawer `position="right" layout="drawer"`
- [ ] 422 no campo, 400 no toast, mensagem de negócio vinda da API
- [ ] Excluir via `$store.confirm`
- [ ] Listagem com `...window.paginated()` + `<x-shared.pagination>`, e filtros em `reload()`
- [ ] Se a listagem é exportável: `<x-shared.export-button>` + `exportFilters()` no componente
- [ ] Item adicionado em `$sections` da sidebar
- [ ] Texto em português, comentário em português, código em inglês

---

## Replicação

Ao copiar o kit para um produto novo: nada aqui precisa mudar. Este documento descreve o
padrão do ecossistema, não do produto. O que muda é `config/spelt-catalog.php`, o `$sections`
da sidebar e as telas em `resources/views/web/`.

**Auditoria:** se uma tela do seu produto não passa no checklist da §11, ela vai divergir das
demais — e a divergência custa mais a cada tela nova que a imita.
