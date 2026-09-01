# CLAUDE.md — starter-kit / Site

## O que é

Template do **site de marketing** de um novo SaaS (Starter Kit) — o terceiro app, ao lado de `api/` (a API) e `platform/` (o app logado). Páginas públicas: **Home, Preços, Plataforma, Sobre, Contato** + um **padrão para páginas específicas**.

Derivado do site do Spelt (`code/www.spelt.com.br`), **abstraindo o que é do Spelt**: a estética (canvas quente, serif de display, um accent de marca) vem de `DESIGN.md`; a marca e o conteúdo são **placeholders** que o produto preenche.

> É só front público. **Sem banco, sem auth, sem API** por padrão. Os CTAs de "Entrar/Começar" apontam para a Plataforma (`config('site.app_url')`).

---

## Ambiente

Roda **em Docker** (VM-Docker via Mutagen), como os outros apps do kit. Não rode `composer`/`npm`/`artisan` na máquina — use o container do site. `env()` só em `config/` (senão `config:cache` quebra).

Boot do container = `init-laravel.sh` (mesmo dos outros apps: storage, composer, key, `npm install`; **não** roda migrate). O `command:` do compose roda `init-laravel.sh && npm run dev -- --host 0.0.0.0`.

---

## Stack

- **Laravel 12** / PHP 8.2 (slim — só as páginas).
- **Blade + Vite + Tailwind + Alpine** (Alpine só para menu mobile e acordeões; sem axios/flowbite).
- **Sem banco**: `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`.

---

## Estrutura

```
site/
├── config/site.php               # FONTE do conteúdo: marca, nav, planos, contato, app_url
├── routes/web.php                # entrypoint enxuto (rotas ficam em routes/web/**)
├── routes/web/Page.php           # rotas das páginas (auto-carregado pelo RouteServiceProvider)
├── app/Http/Controllers/ContactController.php   # form de contato (entrega = gancho do produto)
├── resources/
│   ├── css/app.css               # Tailwind + base (títulos em serif)
│   ├── js/app.js                 # Alpine + collapse
│   └── views/
│       ├── layouts/site.blade.php          # HTML + SEO/OG + header/footer
│       ├── layouts/partials/{header,footer}.blade.php
│       ├── components/site/*.blade.php      # button, container, section, feature, pricing-card, nav-link, logo
│       └── web/{home,pricing,platform,about,contact}.blade.php
│           └── _page.blade.php              # PADRÃO de página nova (copie e ajuste)
└── tailwind.config.js            # design tokens de marketing (troque `brand`)
```

---

## Convenções

### Conteúdo vem de `config/site.php`
Marca (`product.name/tagline/logo`), `app_url` (CTAs), `contact_email`, `nav`, `social` e `plans`. As views leem via `config('site.*')` — **nunca** hardcode nome/planos na blade. Trocar de produto = editar esse arquivo + o `.env`.

### Componentes `<x-site.*>`
Blocos reutilizáveis: `<x-site.container>` (largura), `<x-site.section eyebrow title subtitle center>`, `<x-site.button href variant size>` (`primary`/`secondary`/`ghost`), `<x-site.feature title>` (+ slot `icon`), `<x-site.pricing-card :plan>`, `<x-site.nav-link :route>`, `<x-site.logo>`. Use-os para manter o padrão — não recrie estilos soltos.

### Design tokens (Tailwind)
Estética editorial herdada do `DESIGN.md`: `canvas`/`canvas-raised` (superfícies), `ink`/`ink-mid`/`ink-low` (texto), `line`/`line-soft` (bordas), `well*` (poços de imagem), e **`brand`/`brand-hover`** (o accent). **Trocar a cor do produto = mudar `brand` no `tailwind.config.js`.** Títulos usam `font-display` (serif).

### SEO / Open Graph
Vêm do `layouts/site.blade.php`: cada página define `@section('title', '…')` e `@section('description', '…')`; o layout monta `<title>`, meta description e as tags OG/Twitter. A Home não define título (usa só o nome do produto).

### Padrão de página nova
1. Copie `web/_page.blade.php` → `web/<slug>.blade.php`, ajuste o conteúdo.
2. Registre em `routes/web/Page.php`: `Route::view('/<slug>', 'web.<slug>')->name('site.<slug>');` (com lógica/dados → um Controller no lugar de `Route::view`).
3. Se aparece no menu, adicione em `config('site.nav')`.

### Contato
`ContactController@store` valida o form e, por padrão, **só registra no log** — a entrega real (e-mail para `contact_email`, CRM ou webhook) é um **gancho do produto** (ver o `TODO` no método). O sucesso volta via `session('success')`.

---

## Replicação (novo produto)

1. Copie `site/` para `code/www.<produto>.com/`.
2. `.env`: `PRODUCT_NAME`, `PRODUCT_LOGO`, `APP_PLATFORM_URL` (URL da Plataforma), `CONTACT_EMAIL`, e os `VITE_*` com o host do container do site.
3. `config/site.php`: nav, planos, social.
4. `tailwind.config.js`: `brand`/`brand-hover` para a cor da marca.
5. Substitua os placeholders das páginas (`web/*.blade.php`) e os "poços" de imagem pelas capturas reais.

### Checklist

- [ ] `config/site.php` preenchido (marca, planos, nav, contato).
- [ ] `brand` trocado no Tailwind.
- [ ] CTAs apontando para a Plataforma (`app_url`).
- [ ] Entrega do formulário de contato ligada (email/CRM) no `ContactController`.
- [ ] `title`/`description` de cada página revisados (SEO).
- [ ] Favicon em `public/favicon.ico`.
