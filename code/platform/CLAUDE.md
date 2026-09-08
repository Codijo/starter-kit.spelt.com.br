# CLAUDE.md — starter-kit / Platform

Guia de contexto para o Claude Code neste ambiente. Leia antes de qualquer tarefa.

---

## O que é

**Front (Platform)** de um novo produto SaaS plugado ao Spelt (Starter Kit). Consome a
**API do próprio produto** (o kit `api/`) via token, e **deriva a UI do App do Spelt**
(`app.spelt.com.br`) — listagens, formulários, identidade. O Platform **não fala com o
Spelt direto**: quem integra é a API.

> Spec: `docs/technical/starter-kit.md` no repo `api.spelt.com.br`.
> **Padrão de telas = App.** Consulte o **Customer** (`customer.spelt.com.br`) só quando
> algo divergir (ex.: a mecânica de SSO/token/cookie foi espelhada de lá).

## Ambiente

Roda **exclusivamente em Docker** (container próprio de DEV, a ser criado). **Nunca
execute na máquina do Otávio** `composer`, `artisan`, `npm`, `php` — só dentro do container.

**Boot — `init-laravel.sh`:** padrão único de todos os projetos do Otávio (mesmo arquivo na
API e no Platform), rodado pelo `command:` do compose a cada subida. Idempotente: storage
dirs + permissões 775, `.env` do `.env.example` se faltar, `composer install`, `key:generate`,
limpa caches, `storage:link`, e `npm install` (o Platform tem `package.json`). **Não roda
`migrate`** (o Platform nem tem banco). O `command` do Platform encadeia `npm run dev -- --host 0.0.0.0`
depois do script:
```yaml
command: sh -c "chown 1000:1000 <app> -R && cd <app> && ./init-laravel.sh && npm run dev -- --host 0.0.0.0"
```

## Stack
- Laravel 12 slim · Blade + **Alpine** + **Vite** + **Tailwind v3** + **Flowbite** · axios.
- **Sem banco**: sessão/cache em arquivo (`SESSION_DRIVER=file`). O estado de auth é o cookie
  de token, não uma tabela.
- Design system herdado do App (paleta/fontes/Flowbite em `tailwind.config.js`).

## Autenticação — SSO-only (mecânica do Customer)

O Spelt manda o usuário para `{app_url}?spelt_token=…`, e a **app_url é a RAIZ do produto**
(não `/auth/spelt`). Por isso o token é consumido no **middleware**, não numa rota dedicada:

1. Guard: `EnsureAuthenticated` (`auth.token`) intercepta `?spelt_token=` em **qualquer** URL,
   troca via `App\Support\SpeltSso` (`POST /api/spelt/sso`), grava o cookie
   (`App\Support\TokenCookie`) e redireciona limpando a URL (token é uso único, ~60s). Sem token
   e sem cookie → login. *(Padrão do `CheckApiToken` do SMTP da iPORTO — consumir no middleware
   faz o SSO valer para qualquer app_url, inclusive deep links. `Auth\SsoController` (`/auth/spelt`)
   fica como fallback redundante, reusando o mesmo `SpeltSso`.)*
2. `app_bootstrap.blade.php` lê o cookie server-side → `window.App`; o axios usa **Bearer** na API.
   `401` da API → interceptor do axios manda pro `/logout` (`Cookie::forget`).
3. **Retorno (gestão/conversão):** `managePortal()` usa `account.spelt_panel_url` — o portal
   Customer do Seller **aprendido no payload do SSO** (`panel_url`), gravado na conta. Sem env.

- **DEV — alias obrigatório:** os containers não resolvem o host público da API. Exponha-o como
  **alias de rede** no nginx do deploy (`aliases: [api.<projeto>.<tld>]`) — o nginx roteia por
  server_name, então o `SpeltSso` usa a URL pública direto. (A iPORTO usa host interno + header
  Host; o alias é mais limpo.)
- **DEV — login:** `/auth/dev-login?token=<token da API>` (404 em produção) testa sem o SSO real.

## Estrutura
```
routes/
  web.php                 # landing pública (/login)
  web/Auth.php            # /auth/spelt, /logout, /auth/dev-login
  web/Guard.php           # / (painel), /billing-required  (middleware auth.token)
app/
  Support/TokenCookie.php # cookie do token (mecânica do Customer)
  Http/Middleware/EnsureAuthenticated.php
  Http/Controllers/Auth/{SsoController,LogoutController,DevLoginController}
  Http/Controllers/DashboardController.php
resources/
  js/{app,bootstrap}.js · js/modules/{axios,alpine}.js
  js/stores/me.js        # store global: carrega /me UMA vez (identidade + gating + créditos)
  views/layouts/{guard,public}.blade.php
  views/layouts/partials/{app_bootstrap,sidebar,header}.blade.php   # a SHELL
  views/web/{landing,dashboard,billing_required}.blade.php
tests/
  Support/AlpineContract.php          # lê o par blade↔JS como contrato
  Feature/{AlpineContractTest,ScreenSmokeTest}.php
```
- **Rotas** em `routes/web/**` (auto-carregadas pelo `RouteServiceProvider`, grupo `web`).
> ⚠️ **Antes de criar QUALQUER tela, leia o [`DESIGN.md`](DESIGN.md).** Ele responde "como se
> escreve uma tela aqui": idioma de URL, anatomia index/create/show, onde o `@vite` da tela vai
> (errar isso quebra a tela inteira), o layout de duas colunas do detalhe, o drawer de edição,
> o tratamento de erro e o checklist final. As convenções não são óbvias e não estão nesta página.

- **Shell (padrão App):** `layouts/guard.blade.php` = sidebar + header + conteúdo. A layout
  chama `$store.me.load()` uma vez; header/sidebar/páginas consomem `$store.me`
  (`user`, `account`, `subscription`, `entitlements`, `creditBalance`, `hasAccess`,
  `managePortal()` → portal do Spelt). **O produto declara sua navegação no array `$sections`
  do `partials/sidebar.blade.php`** (acordeão dirigido por dados: item cuja rota ainda não existe
  é ignorado, então dá para declarar o menu inteiro antes das telas) e as telas em
  `views/web/<dominio>/<recursos>/` — ver `DESIGN.md` §2 e §8.

## Conversão — o produto INSTIGA, o Customer do Spelt converte

Regra de negócio do kit: o produto **nunca** processa pagamento/plano — só empurra o usuário
para o portal Customer do Spelt (via `$store.me.managePortal(target)`), onde a gestão e a compra
acontecem. A instigação vive no produto:

- **`$store.me.nudge()`** (em `js/stores/me.js`) — deriva do `/me` o nudge mais relevante (ou
  `null`): assinatura inativa → trial acabando (via `trial_ends_at`, pois o enum de status do
  mirror **não tem** `trial`) → créditos baixos/zerados (limite 5, ajuste por produto).
- **`partials/conversion_nudge.blade.php`** — banner global no topo do `layout.guard`, dirigido
  pelo `nudge()`; o CTA chama `managePortal(portalTarget(action))` (deep-link `subscription`/
  `credits` — honrado quando o portal Customer suportar redirect pós-SSO; senão cai na raiz).

Para nudges pontuais (ex.: pós-conversão de valor numa tela), reutilize o mesmo `nudge()`/
`managePortal()` — não crie um caminho de billing próprio no produto.

## Testes — `composer test`

Erro de front **não derruba a resposta**: a tela responde 200 e quebra no clique. Foi assim
que passaram, em produto real, `toggleTerm is not defined` (duas vezes) e um `<template x-if>`
dentro de `<svg>`. Quem descobria era o cliente abrindo a tela.

Duas camadas, nenhuma delas com navegador, ambas rodando em menos de dois segundos:

| Suíte | O que amarra |
|---|---|
| `AlpineContractTest` | Todo método chamado numa expressão Alpine existe num script que a tela declara no próprio `@vite`; todo `x-data="nome()"` tem registro correspondente; todo JS que registra um componente pertence a alguma tela. |
| `ScreenSmokeTest` | Toda rota do guard responde 200, manda quem não tem sessão para o login, e renderiza componente que existe. Pega rota sem view, Blade que não compila, `<x-…>` inexistente e variável que a view espera e o controller não passa. |

O contrato lê o `@vite` do blade como fonte da verdade — é o próprio blade que declara quais
scripts o sustentam, e é assim que um mixin espalhado (`...window.offerTermsMixin()`) entra na
conta sem caso especial.

Num kit recém-clonado as asserções do contrato passam vazias, de propósito: **ligam sozinhas na
primeira tela de produto**. Duas coisas pedem manutenção conforme o produto cresce:

- **`AlpineContract::EXPRESSION_PROPS`** — props de componente que carregam expressão Alpine
  (`<x-shared.side-action action="salvar()">` é uma chamada escondida atrás de um prop). Há um
  teste que confronta o mapa com os `@props`: prop documentado como "Expressão" e ausente do
  mapa quebra a suíte.
- **A âncora de `encontra as telas atrás do guard`** — acrescente ali as rotas do seu produto.
  É o que garante que a lista continua *completa*, e não só não-vazia.

> ⚠️ Ao mexer no parser, dois enganos que passam calados: `:prop` num `<x-…>` é atributo **PHP**,
> não bind do Alpine (ler como Alpine faria `route()` virar "método inexistente" em quase toda
> tela); e estrela dupla em `glob()` **não é recursiva** no PHP — vale por um único segmento de
> caminho, e o teste passa sobre o conjunto vazio.

## Estado (Fase 1 — Platform)
- ✅ Esqueleto (Vite/Tailwind/Alpine/axios) · SSO + dev-login · guard + logout.
- ✅ **Shell rica** (padrão App): sidebar colapsável, header com menu do usuário
  (nome/avatar + "Minha conta" → portal Spelt), dashboard com KPIs (`/me`), store `me` global.
- ✅ **Camada de conversão** (nudge banner global + `managePortal(target)`).
- ✅ **Kit de tela** (2026-09-04, aprendizados da Fábrica de Lead): `DESIGN.md` com as
  convenções; `x-shared.side-{card,action,info,copy}` para a coluna de controles do detalhe;
  `x-ui.modal` com `position`/`layout=drawer`; `js/modules/helpers.js` (`copyText`,
  `formatDate`); sidebar em acordeão dirigido por dados.
- ✅ **Testes de tela** (2026-09-05): contrato blade↔Alpine + fumaça das rotas do guard,
  `composer test`. Cada asserção foi verificada por mutação — falha quando deve, e diz qual
  tela e qual nome.
- ✅ **Paginação padrão** (2026-09-06): dez por página em toda listagem, decidido em
  `App\Support\Pagination` (API) e desenhado por `x-shared.pagination` (Platform). Instala-se
  espalhando `...window.paginated()` — ver `DESIGN.md` §9.
- ✅ **Exportação** (2026-09-06): tela de Exportações com acompanhamento até "Pronto",
  `x-shared.export-button` levando os filtros da listagem, e download autenticado. A máquina
  (fila, prazo, expurgo) fica na API — ver o `CLAUDE.md` de lá.
- ⬜ Próximo: telas de valor do produto (na cópia do kit), seguindo o `DESIGN.md`.
