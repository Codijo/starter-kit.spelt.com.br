# Starter Kit — Platform

Front (Blade + Alpine + Vite + Tailwind) de um novo produto plugado ao Spelt. Consome a
**API do produto** (o kit `api/`) e deriva a UI do App do Spelt. Ver `CLAUDE.md`.

## Requisitos
- PHP 8.2+ · Node 20+ · a **API do produto** rodando (o container `api.*` do kit).
- **Sem banco** (sessão/cache em arquivo).

## Setup (rodar dentro do container)
```bash
cp .env.example .env
# aponte PRODUCT_API_URL para a API do produto (o container api.*)
composer install
php artisan key:generate
npm install
npm run dev        # ou: npm run build
```

## Fluxo de autenticação
- **Real:** o portal Customer do Spelt redireciona para `/auth/spelt?spelt_token=...` →
  o Platform troca o token com a API e grava o cookie de sessão.
- **DEV (testar sem SSO real):**
  1. Gere um token na API (no container da API):
     ```bash
     php artisan dev:token          # opcional: --seats=10 --name="Conta X" --email=a@b.com
     ```
  2. Acesse a URL impressa (`/auth/dev-login?token=...`) → cai no painel.

## Rotas
| Rota | O que faz |
|---|---|
| `GET /login` | Landing pública |
| `GET /auth/spelt?spelt_token=` | Handoff SSO do Spelt → cookie → painel |
| `GET /auth/dev-login?token=` | **DEV** — atalho de teste (404 em produção) |
| `GET /` | Painel (consome `/me`) — exige o cookie |
| `GET /billing-required` | Bloqueio quando sem acesso |
| `GET /logout` | Limpa o cookie |
