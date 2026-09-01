# Starter Kit — API

Template de **API** para um novo produto SaaS já plugado ao **Spelt**. Enxuto por
design (bridge leve): traz só a casca Laravel, as convenções e a **camada de
integração `App\Spelt`** — o comercial (billing, planos, suporte, notificações)
fica no Spelt.

> Spec completa: `docs/technical/starter-kit.md` no repo `api.spelt.com.br`.
> Briefs de produtos de referência: `docs/starter-kit-ideas/`.

---

## Requisitos
- PHP 8.2+ · Laravel 12 · MySQL · Redis (cache/fila/sessão)
- Uma conta no Spelt com a integração criada (Marketplace de Integrações):
  `SPELT_API_KEY`, `SPELT_WEBHOOK_SECRET`, `SPELT_CUSTOMER_PORTAL_URL`.

## Setup (rodar dentro do container)
```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan test          # opcional — usa sqlite em memória
```

## Estrutura
```
app/
  Models/                            # namespaces por domínio — o Model manda (ver CLAUDE.md)
    Core/Account/Account.php          # = Spelt Tenant espelhado; gate, entitlements, saldo
    Core/Account/User.php             # usuário do produto (SSO-only, sem senha gerida)
    Core/Account/PersonalAccessToken.php # token Sanctum (captura IP/UA)
    Billing/Subscription.php          # espelho do estado de billing + snapshot de entitlements
    Billing/CreditLedger.php          # ledger append-only de créditos (saldo = SUM(amount))
    Spelt/WebhookEvent.php            # idempotência dos webhooks recebidos
  Contracts/
    ProductProvisioner.php            # extensão do produto (provision/onPlanChanged/teardown)
  Services/Spelt/
    NullProductProvisioner.php        # padrão no-op (produtos "só CRUD")
  Traits/
    Concerns/Scope.php                # forCurrentAccount() — isolamento por account_id
    Core/Account/Authorization.php    # injectAccountId(), ensureAccountOwnership()
routes/
  api.php                             # rotas globais/públicas (/ping)
  api/Spelt/{WebhookRoute,SsoRoute}.php · api/Core/Account/MeRoute.php
  api/Example/ExampleRoute.php         # exemplo gated (o produto remove) · api/Api.php (fallback 404)
database/migrations/                   # por domínio: Core/Account/* · Billing/* · Spelt/*
```

## Como um produto usa o kit
1. Copie este diretório para o seu produto e ajuste `.env` (`SPELT_*`, `PRODUCT_*`).
2. Construa o **valor** do produto (models/telas próprios), sempre isolando por
   `forCurrentAccount()`.
3. Se o produto tem infra por conta, implemente `App\Spelt\Contracts\ProductProvisioner`
   e troque o binding em `AppServiceProvider` (o padrão é o no-op).
4. Consuma o saldo de créditos e os entitlements da `Account` (`creditBalance()`,
   `entitlement()`, `allows()`, `withinLimit()`).

## Integração com o Spelt (mapa)
| Primitivo | Onde vive | Status |
|---|---|---|
| Tenancy leve (`forCurrentAccount`) | `Models\Core\Account\*`, `Traits\*` | ✅ |
| Ledger de créditos + entitlements | `Models\Billing\{Subscription,CreditLedger}`, `Services\Billing\{CreditService,EntitlementService}` | ✅ |
| Provisioner (contrato + no-op) | `Contracts\ProductProvisioner`, `Services\Spelt\NullProductProvisioner` | ✅ |
| Webhook receiver (`POST /api/spelt/webhook`) | `Http\Controllers\Spelt\WebhookController` + `Services\Spelt\WebhookProcessor` + `Jobs\Spelt\ProcessWebhookJob` | ✅ |
| SSO exchange (`POST /api/spelt/sso`) | `Http\Controllers\Spelt\SsoController` + `Services\Core\Account\AccountService` | ✅ |
| Gate de acesso (`spelt.access`) | `Http\Middleware\EnsureSpeltAccess` | ✅ |
| `SpeltClient` (External API v1) | `Services\Spelt\SpeltClient` | ✅ |
| `spelt:reconcile` (command) | `Console\Commands\Spelt\ReconcileCommand` | ✅ |

> **API completa** (Bloco 1 esqueleto/dados + Bloco 2 comportamento). 15 testes Pest verdes.
> Próximo: o **Platform** (front que consome esta API).
