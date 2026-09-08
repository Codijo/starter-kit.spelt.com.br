# Spelt Starter Kit — Especificação de Arquitetura

> **Status:** Fase 0 — spec / definição. Nenhum código escrito ainda.
> **Local do kit:** `code/starter-kit.spelt.com.br/`
> **Origem conceitual:** este documento é o guia [`resources/integration-guides/laravel.md`](../../resources/integration-guides/laravel.md) **materializado como aplicação completa e rodável** — em vez de o Seller copiar trechos, ele ganha o app inteiro já fiado ao Spelt, com a UI dos nossos portais.
> **Criado em:** 2026-08-10 · **Consolidado em:** 2026-08-11.

---

## 1. Resumo executivo

O **Starter Kit** é um template Laravel para construir **novos produtos SaaS que já nascem plugados ao Spelt**. Todo o comercial/administrativo — billing, planos, cobrança, cupom, fatura, suporte, afiliados, fiscal, gateway, **notificações ao cliente** — **fica no Spelt**. O produto novo só carrega:

1. a **casca Laravel** enxuta (auth via SSO, convenções de resposta, storage, log, tenancy leve);
2. o **kit de UI/UX** dos portais do Spelt (componentes `x-shared.*`, Tailwind, Alpine), reaproveitado pela camada **"Platform"**;
3. a **camada de conexão com o Spelt** (SSO receiver, webhook receiver, cliente da External API, gate de acesso, **entitlements**, **provisionamento por fila/command**).

Você foca no **serviço**; a **gestão** é do Spelt. O produto se copia para um diretório próprio e você constrói só o *valor* (WhatsApp API, Webhook.site, Link na Bio…).

**O que o Starter Kit NÃO é:** não é um Spelt reduzido. Não herda a máquina interna do Spelt (`TenantType`, hierarquia `parent_tenant_id`, os 4 contextos de API, `livemode`, gateways, proration, wallet, impersonation, access-grant delegado).

---

## 2. Decisões travadas

| # | Decisão | Escolha | Data |
|---|---|---|---|
| 1 | **Derivação da base** | **Bridge leve** — herda só UI + convenções + camada de integração | 08-10 |
| 2 | **Superfície da Platform** | **Cliente final** — entra por SSO `/auth/spelt`. UI deriva de **`app.spelt.com.br`** (App/cockpit do Seller). *Atualizado 08-28:* era `customer.spelt.com.br`; trocado porque o App tem listagens/formulários/identidade mais ricos, melhores para um produto de muitas telas. A superfície servida continua sendo o cliente final. | 08-10 / 08-28 |
| 3 | **Relação de código** | **Template que se copia** (sem sync automático; ver §17) | 08-10 |
| 4 | **Topologia** | **Dois apps** (API token-Sanctum + Platform Blade/Alpine via axios) | 08-10 |
| 5 | **Grão de conta** | **Conta = Spelt Tenant, N usuários** — isolamento por `account_id` | 08-10 |
| 6 | **Identidade** | **SSO-only, sem gerir senha** — sessão persiste; re-entrada via SSO do Spelt | 08-11 |
| 7 | **Assinaturas por conta** | **1 (`hasOne`)**, modelada para promover a N se um serviço "crescer" | 08-11 |
| 8 | **Tamanho** | **Kit mínimo** — Media/File/Notification só quando o produto precisar | 08-11 |
| 9 | **Notificações** | **Vivem no portal Customer do Spelt** — kit não tem central de notificações | 08-11 |
| 10 | **Usage-based** | **Fora por padrão, acoplável barato** (§15) | 08-11 |
| 11 | **Entitlements** | **Spelt é a fonte** (PlanFeature/PlanMetric); kit lê via `GET /plan/{id}` e cacheia | 08-11 |
| 12 | **Enforcement de downgrade** | **Hook por produto** (`onPlanChanged`) — kit não impõe política | 08-11 |
| 13 | **Namespaces** | **O Model define o domínio; Controllers/Services/Requests/Jobs/traits seguem a mesma cadência.** Domínios: `Core\Account`, `Billing`, `Spelt` (integração). Tabelas prefixadas (`core_*`/`billing_*`/`spelt_*`). Contratos do produto em `App\Contracts`; services em `App\Services\<Domínio>\*`. *(Refinado 08-28 — era o flat `App\Spelt\*`.)* | 08-11 / 08-28 |
| 14 | **Portal do SSO reverso** | **Aprendido no SSO** (`panel_url` → `accounts.spelt_panel_url`) | 08-31 |
| 15 | **Escopo desta sessão** | **Só a spec** | 08-10 |

---

## 3. Casos de referência — o eixo do kit

O kit **não muda** entre serviços. O que varia é **quanto de provisionamento** e **quão ricos são os limites de plano**:

| Serviço | Provisionamento | Entitlements (limites do plano) | Downgrade | Estado |
|---|---|---|---|---|
| **Link na Bio** | trivial (slug/página) | # links, domínio custom, analytics | soft-cap (esconde excedente) | leve |
| **Webhook.site** | leve (namespace/token) | retenção, req/min, # endpoints | podar/limitar | médio, stateful |
| **WhatsApp API** | **pesado** (worker de sessão WA Web por número, QR, reconexão) | # números, throughput de msg | **desconectar capacidade** | pesado, long-running |

As **três costuras** que tornam o kit genérico: **entitlements** (§9), **provisionamento/teardown** por fila+command (§10) e **gate de acesso** (§8.3). **WhatsApp é o caso que mais força o design** (infra cara, long-running, downgrade destrói capacidade); Link na Bio é o trivial. Se o kit aguenta WhatsApp, aguenta os outros.

> A **Platform pode ser rica** (QR code, status de sessão do WhatsApp) — só a *gestão comercial* fica no Spelt; o valor operacional vive no produto. Produtos que crescerem muito podem ganhar dono/time dedicado e divergir do kit sem prejuízo.

> **Princípio da infra própria:** a maioria dos produtos do kit traz a **própria infra** para entregar o serviço (engine de match, pipeline de dados, pool de IPs para SMTP/TELNET) — poucos serão "apenas CRUD". O kit é a **casca comercial/identidade** e fica **fora** dessa infra: não tenta provê-la nem constrangê-la.

---

## 4. Modelo mental — os três atores

```
┌──────────────────────────────────────────────────────────────────────────┐
│  SPELT  (plataforma comercial + identidade)                                │
│  billing · planos · faturas · pagamento · suporte · fiscal · notificações  │
│  External API v1 · Webhooks (25 eventos) · SSO · Checkout hospedado        │
└──────────────────────────────────────────────────────────────────────────┘
        ▲  webhooks / API                         │  SSO /auth/spelt
        │  (server-to-server)                      ▼  (cliente entra no produto)
┌──────────────────────────────────────────────────────────────────────────┐
│  PRODUTO DO SELLER  (= Starter Kit copiado)                                │
│  ┌───────────────────────┐        ┌──────────────────────────────────┐    │
│  │  API  (token Sanctum)  │◀──────▶│  PLATFORM  (Blade + Alpine)       │    │
│  │  deriva de api.*       │  axios │  deriva o shell de customer.*     │    │
│  │  + App\Spelt\*         │        │  + telas do produto (QR, etc.)    │    │
│  └───────────────────────┘        └──────────────────────────────────┘    │
│         o VALOR do produto vive aqui — a GESTÃO vive no Spelt              │
└──────────────────────────────────────────────────────────────────────────┘
```

| Ator | Quem é | Papel |
|---|---|---|
| **Spelt** | A plataforma | Cobra, emite fatura, gerencia assinatura, notifica o cliente, identidade comercial. |
| **Seller** | Você, dono do produto | Publica o produto, integra ao Spelt, vende assinaturas via Spelt. |
| **Cliente final** | Cliente do Seller | Assina via Spelt, **usa o produto**, é cobrado pelo Spelt. |

### Fluxo ponta a ponta

```
1. Cliente compra    → Loja Pública / Checkout do Spelt
2. Spelt cobra       → dispara checkout.completed + subscription.activated + invoice.paid
3. Produto provisiona → webhook cria a Account, lê entitlements do plano, dispara ProvisionAccountJob
4. Cliente entra     → SSO /auth/spelt → sessão no produto
5. Cliente usa       → gate valida assinatura ativa + entitlements a cada request protegida
6. Gestão de conta   → "Gerenciar assinatura" → SSO reverso → portal Spelt (paga, cancela, up/down)
7. Mudanças          → Spelt dispara webhooks → produto ajusta acesso e reprovisiona (onPlanChanged)
```

---

## 5. Topologia — dois apps

O Starter Kit é um **par de templates** que se copia junto:

```
code/starter-kit.spelt.com.br/
├── docs/             # spec técnica (este doc) + ideias de produto
└── code/
    ├── api/          # template da API   — deriva enxuto de api.spelt.com.br
    ├── platform/     # template do front — deriva o shell de customer.spelt.com.br
    └── www/          # template do site institucional — OPCIONAL, ver abaixo
```

Ao criar um produto novo (ex.: `webhooks.io`), copia-se o par e renomeia para `code/api.webhooks.io` + `code/platform.webhooks.io`, espelhando a convenção de siblings do Spelt.

> Escolha registrada: **dois apps** (não monolito) por fidelidade à arquitetura do Spelt e reuso literal do shell do Customer. Custo assumido: CORS entre os dois + dois deploys por produto. Preferência explícita do dono do projeto por manter API e front separados.

> **Terceiro app OPCIONAL — `www` (site institucional):** um produto pode adicionar `www.<projeto>.com`
> (+ apex). É **Laravel + Vite, mesmo padrão do `platform`** (mesmo `Dockerfile`/`.deploy`/workflow) — só
> muda o conteúdo. **Não faz parte do template mínimo do kit** (que é api + platform); o Psst tem um,
> criado à parte. O runbook de deploy (`§18.1`) cobre os três. Envs próprios do www: `APP_PLATFORM_URL`
> (pra onde o CTA de acesso leva), `CONTACT_EMAIL`, `PRODUCT_NAME`.

---

## 6. Template da API — herda / corta / adiciona

### 6.1 Herda (enxuto)

| Categoria | O que traz |
|---|---|
| Base | Laravel 12 / PHP 8.2, estrutura de diretórios, FormRequest, error handler global |
| Auth | Sanctum (token) — **um guard só** (`api`), **SSO-only** (sem senha), sem `tenant.type` |
| Tenancy leve | `Scope` reduzido → `scopeForCurrentAccount()`; `Authorization` reduzido → `injectAccountId()`, `ensureAccountOwnership()` |
| Convenções | Padrão de resposta JSON (200/201/400/404/422/500), shape do `LengthAwarePaginator` |
| Storage | Contrato `BucketInterface` (S3/DO switch por `.env`) — igual ao Spelt |
| Observabilidade | Activity log simplificado |

### 6.2 Corta (fica no Spelt)

Billing inteiro, Support, Affiliate, Fiscal, Gateway + drivers, Communication, **central de notificações ao cliente**, Backoffice, Catalog **CRUD** de planos, `TenantType`/hierarquia, os 4 contextos, `ResolveDelegatedTenant`, impersonation, `EnsureTenantType`, `livemode`/`LivemodeScope`, `DomainResolverService` multi-Seller, e a **External API v1** (o kit é *cliente* dela, não servidor).

### 6.3 Adiciona (o núcleo do kit)

Camada de integração (§8), **Entitlements** (§9) e **Provisionamento** (§10),
organizados por domínio (`Services\Spelt\*` para a fronteira com o Spelt,
`Services\Billing\*` para créditos/entitlements, `Models\Spelt\WebhookEvent`,
`App\Contracts\ProductProvisioner`). Ver a convenção de namespaces na decisão #13 e
no `CLAUDE.md` do template.

---

## 7. Template da Platform — herda / corta / adiciona

**Deriva de `app.spelt.com.br`** (App/cockpit do Seller) — decisão de 2026-08-28: o App tem listagens, formulários e identidade mais ricos que o portal Customer, e "fala melhor" com um produto de muitas telas. Traz **só o design system e o shell** — não a lógica de negócio/gestão do App. **A superfície servida continua sendo o cliente final** (entra por SSO do portal Customer); muda apenas a *fonte* do kit de UI.

| | |
|---|---|
| **Herda** | Layout guard · componentes `x-layout.*`, `x-ui.*` e `x-shared.side-*` · stores Alpine globais (`flash`, `confirm`, `me`) · módulo axios (adaptado) · helpers (`copyText`, `formatDate`) · tema Tailwind · **[`DESIGN.md`](../../code/platform/DESIGN.md)** |
| **Corta** | Todas as telas de domínio/gestão do App (billing, planos, clientes, faturas, suporte, afiliados, comunicação, cockpit/dashboards) e o multi-conta/acesso delegado — vivem no Spelt. Fica só o **shell** (layout, nav, componentes `x-shared.*`, auth de sessão, axios) |
| **Adiciona** | Shell + telas do produto (vazias no template), tela `billing-required` (bloqueio com botão SSO reverso), UI operacional que o produto exigir (ex.: QR/status de sessão) |

> ⚠️ **As convenções de tela vivem no [`DESIGN.md`](../../code/platform/DESIGN.md) do template**,
> não nesta spec: idioma de URL, anatomia `index`/`create`/`show`, onde o `@vite` da tela vai,
> layout de duas colunas do detalhe (Controles / Informações / Ações Rápidas), drawer de edição,
> tratamento de erro (422 no campo, 400 no toast) e o checklist da primeira tela.
>
> Ele foi escrito em 2026-09-04, depois que a Fábrica de Lead (2º produto do kit) errou nove
> dessas convenções na primeira tela. Todas já existiam nas implementações vivas; nenhuma
> estava escrita. **Esta spec cobre a costura com o Spelt; o `DESIGN.md` cobre a UI.**

**Branding do produto:** fixo por config (`PRODUCT_NAME`, `PRODUCT_LOGO`) — produto de um único Seller, não resolve branding por domínio.
**axios adaptado:** sem `X-Current-Domain*`; a Platform fala com **uma** API (a do próprio produto), autenticada por token.

---

## 8. Camada de integração com o Spelt

Os 5 primitivos do [guia base](integration-base.md), no recorte **cliente-final**.

### 8.1 SSO receiver — entrada principal (Platform)

O Spelt manda o Customer para `{app_url}?spelt_token=…` e a **app_url é a RAIZ do produto**. Por
isso o token é consumido no **middleware do guard** (`EnsureAuthenticated`), que intercepta
`?spelt_token=` em QUALQUER URL — não numa rota `/auth/spelt` dedicada (mantida como fallback).

1. Valida em `GET {SPELT_API_URL}/api/public/sso/validate?token=...` (uso único, 60s).
2. Payload: `tenant_id`, `tenant_email`, `tenant_name`, `panel_url`, `subscriptions[]`
   (cada uma com `status`, **`has_access`**, `trial_end`).
3. `firstOrCreate` da **Account** (por `spelt_tenant_id`) — grava também `spelt_panel_url`
   (o portal Customer do Seller, aprendido daqui) + `firstOrCreate` do **User** (por email).
4. Sincroniza assinatura + entitlements via **`SubscriptionSync`** (trial conta como ativo).
5. Grava o cookie de sessão e redireciona limpando a URL (token é uso único). **Sem senha.**

> **Dev:** os containers não resolvem o host público da API — exponha-o como **alias de rede** no
> nginx do deploy (`aliases: [api.<projeto>.<tld>]`), aí o `SpeltSso` usa a URL pública direto.

### 8.2 Webhook receiver — fonte de verdade do entitlement (API)

```
POST /spelt/webhook       Authorization: Bearer {SPELT_WEBHOOK_SECRET}
```
- Valida Bearer; responde **200 em < 5s**; enfileira (job). **Idempotência** por `spelt_event_id`.

**Todos os `seller.subscription.*`** passam pelo **`SubscriptionSync`** (o `data` do webhook é o
`SubscriptionDTO` da External v1, com `status` + `has_access` autoritativos): **trial conta como
ativo com acesso**; `created` é tratado (o trial NÃO emite `activated`); os valores do Spelt
(`canceled`/`expired`/`paused`/`pending_payment`) são traduzidos pro enum do produto
(`active`/`inactive`/`past_due`/`cancelled`). É o **mesmo** mapeamento do SSO e do reconcile.

| Evento Spelt | Ação no produto |
|---|---|
| `seller.customer.created` / `activated` | cria/reativa `Account` → `ProvisionAccountJob` |
| `seller.customer.suspended` / `cancelled` | suspende/encerra `Account` → teardown/suspend |
| `seller.subscription.*` (created, activated, updated, upgraded, downgraded, cancellation_scheduled, cancelled, renewed, expired, trial_ended, payment_failed, access_blocked, access_restored) | `SubscriptionSync::apply` — reflete status + `has_access` + entitlements; dispara `onPlanChanged` (§10) no diff |
| `seller.invoice.paid` | destrava a assinatura existente (reforço) |
| `seller.credit.granted` | **soma ao saldo de créditos** (§8.6) — origem (renovação **ou** compra avulsa) é indiferente |
| `seller.credit.expired` | **subtrai do saldo de créditos** (§8.6) |

> ⚠️ **A ordem de entrega NÃO é garantida.** Numa compra real o `credit.granted` costuma chegar
> **antes** do `customer.created` (a conta ainda não existe). Por isso os handlers de crédito usam
> `accountOrCreate` (não `account`) — o crédito sempre pousa numa conta placeholder (`name = 'Conta'`),
> e `AccountService::ensure()` **enriquece o nome** quando o `customer.created`/SSO chega depois. Se o
> handler dependesse da conta já existir, a concessão era pulada e o event-id ficava `PROCESSED` — nem
> o reenvio recuperava. **Idempotência dos DTOs:** os campos variam por evento (o `data` é o DTO da
> External v1) — `customer.*` traz o customer como **`id`** (+`name`/`email`); `subscription.*`/`invoice.*`
> trazem **`customer_id`**; `credit.*` traz `customer_id` + **`quantity_granted`/`quantity_remaining`** +
> `id` do lote. Leia os dois (`customer_id ?? id`).
>
> **Reentrega (`WebhookController`):** idempotente por `spelt_event_id`. Evento novo → processa;
> evento **`FAILED`** reenviado → reprocessa (handlers idempotentes, recuperação de operador); evento
> **`PROCESSED`** reenviado → no-op. A rede de segurança real para grant perdido é o `spelt:reconcile`
> (espelha os lotes `active` do Spelt, idempotente por `spelt_credit_id`), **não** o reenvio.

### 8.3 Access gate — middleware `EnsureSpeltAccess`

- **Local-first:** lê `account_subscription.has_access && status === active` (trial já vira `active`).
- **Reconciliação:** `spelt:reconcile` bate em `GET /customer/{id}/subscription` (**fonte da
  verdade** — status + `has_access` autoritativos) e reaplica via `SubscriptionSync`. Cura webhook
  perdido, evento inexistente (o trial não emite `activated`) ou endpoint desligado. Staleness em
  dois níveis (`--stale-hours`, `--all`, `--account=`). **Requer chave `splt_live_`** para enxergar
  clientes de produção — a `splt_test_` só vê test mode (relevante em dev).
- Sem acesso → **403** (API) / redirect `billing-required` (Platform, com botão SSO reverso).

### 8.4 External API client — `SpeltClient` (server-to-server)

`config/services.php` (`spelt.*`), Bearer `splt_live_`/`splt_test_`:

| Método | Endpoint | Uso |
|---|---|---|
| `getSubscription($id)` | `GET /seller/external/v1/subscription/{id}` | detalhe pontual |
| `getCustomerSubscriptions($customerId)` | `GET /seller/external/v1/customer/{id}/subscription` | **reconcile** (fonte da verdade) |
| `getPlan($id)` | `GET /seller/external/v1/plan/{id}` | **entitlements** (§9) |
| `reportUsage(...)` | `POST /seller/external/v1/usage-record` | usage-based (opt-in, §15) |
| `generateReverseSso($customerId)` | `POST /seller/external/v1/customer/{id}/auth-token` | "Gerenciar assinatura" |
| `createCheckoutSession(...)` | `POST /seller/external/v1/checkout-session` | upsell no produto (opcional) |

> ⚠️ **`SPELT_API_KEY` é lida via `config('services.spelt.api_key')` — worker longo-vivo cacheia no boot.**
> Trocar a chave (placeholder → `splt_live_`) NÃO chega ao worker do Horizon/queue que já roda; ele segue
> com a antiga até **reiniciar**. `config:clear` não basta (só limpa o arquivo; o processo não relê). Um
> tinker/request novo "funciona" e mascara. Sintoma real: `getPlan` 401 no worker enquanto funciona no
> tinker. **Ao trocar a chave, reinicie o container do Horizon** (dev e produção).

### 8.5 SSO reverso (produto → portal Customer do Spelt)

Botão **"Gerenciar assinatura"** → `SpeltClient::generateReverseSso(spelt_tenant_id)` → o produto
redireciona para o **`/sso`** do portal Customer, derivando **scheme+host** do `panel_url`
aprendido no login (`accounts.spelt_panel_url`) — **NÃO** há env fixa:
```
{host do portal Customer}/sso?spelt_sso_token={token}
```
O portal consome em `/sso` (tira o prefixo `splt_sso_`, grava o cookie de sessão, vai pra
`/guard`). `/sso` e `/guard` vivem na RAIZ do portal (sem `/@{slug}` — o token já identifica o
Customer). Deep-link opcional via `?redirect=/guard/...`.

### 8.6 Sincronização de créditos (saldo consumível) — núcleo

Padrão validado nos **dois** casos de referência ([Fábrica de Lead](../starter-kit-ideas/fabrica-de-lead.md), [MailValidation](../starter-kit-ideas/mail-validation.md)): quota consumível (leads, e-mails validados) **não é primitivo novo** — é o módulo de **Créditos** que o Spelt já tem.

- O produto mantém um **ledger de saldo local** alimentado por `seller.credit.granted` (+) e `seller.credit.expired` (−). A **origem** do grant é indiferente: renovação recorrente **ou** compra avulsa (pagamento único) chegam pelo mesmo evento.
- **O consumo é 100% do produto.** O Spelt concede; o produto debita ao entregar valor. O débito **não é obrigatoriamente 1:1** — o produto define o custo por operação (ex.: no MailValidation, mecânica custa 1, existência N, uso M).
- **Saldo = concedido (Spelt) − consumido (local).** O Spelt não precisa saber do consumo (a menos que se opte por reportar — decisão registrada nos briefs).
- **Expiração:** honrar `credit.expired`; não reimplementar a regra de expiração localmente.
- **Idempotência por `spelt_credit_id`:** `grant`/`expire` são `firstOrCreate` por `(type, spelt_credit_id)` — webhook duplicado, reenvio e passada do reconcile não re-lançam.

> **Config do plano no Spelt — a franquia recorrente é `PlanCreditGrant.quantity`, o trial é `trial_value`.**
> O crédito do **ciclo pago** vem de `quantity` (ex.: plano Time = 120/mês); o do **trial** vem de
> `trial_value`/`trial_mode` (ex.: 5). **Não existe coluna `value`** — se a franquia recorrente ficar
> em branco, o pagante recebe 0/ciclo. Como o produto costuma consumir crédito por operação de forma
> **incondicional**, a franquia recorrente TEM que estar setada no plano, senão o cliente pago trava
> assim que a cortesia do trial acaba.
>
> **Loop pago validado E2E (2026-08-31, Psst):** trial → "Assinar agora" (`TrialConversionService::convertNow`
> → `active` + fatura) → pagamento → `invoice.paid`+`subscription.renewed`+`credit.granted(120)` → produto
> soma para `5→125` em **dois lotes distintos** (cortesia + franquia coexistem; a cortesia expira no fim
> original do trial).

```php
// spelt_credit_ledger — append-only; saldo do account = SUM(amount)
Schema::create('spelt_credit_ledger', function (Blueprint $t) {
    $t->id();
    $t->foreignUlid('account_id')->constrained();
    $t->string('type');                                  // grant | expire | consume
    $t->integer('amount');                               // + concessão · − expiração/consumo
    $t->string('spelt_credit_id')->nullable()->index();  // referência ao grant do Spelt
    $t->string('reference')->nullable();                 // o que consumiu (product-side)
    $t->json('meta')->nullable();
    $t->timestamps();
});
```

---

## 9. Entitlements — plano → capacidades/limites

**Fonte da verdade: o Spelt.** Os limites vivem como `PlanFeature`/`PlanMetric` do plano. O produto **consome**, nunca define billing.

- **Leitura:** `SpeltClient::getPlan($planId)` (`GET /plan/{id}` já retorna features). Chamado no bootstrap e a cada `subscription.upgraded/downgraded`.
- **Snapshot:** as features resolvidas são gravadas em `account_subscription.entitlements` (JSON) — leitura O(1) no request, sem chamar o Spelt a cada checagem. `spelt:reconcile` re-hidrata para curar drift.
- **Shape esperado:**
  ```json
  { "whatsapp_numbers": 5, "monthly_messages": 10000, "custom_domain": true, "retention_days": 30 }
  ```
- **Helper (produto consome):**
  ```php
  $account->entitlement('whatsapp_numbers');   // 5  (limite numérico)
  $account->allows('custom_domain');           // true (flag)
  $account->withinLimit('whatsapp_numbers', $currentCount); // bool
  ```
- **Otimização futura (Spelt-side, opcional):** enriquecer o payload de webhook/SSO com as features do plano elimina a chamada extra a `GET /plan/{id}`. Não é bloqueante — o caminho de leitura já existe.

---

## 10. Provisionamento & ciclo de vida da conta (filas + commands)

O kit entrega os **ganchos**; cada produto implementa a lógica. Contrato:

```php
// App\Spelt\Contracts\ProductProvisioner  (o produto implementa)
public function provision(Account $account): void;                          // criar infra conforme entitlements
public function onPlanChanged(Account $a, array $old, array $new): void;      // up/down → reconciliar
public function teardown(Account $account): void;                            // cancel/suspend → liberar infra
```

- **Jobs prontos (fila):** `ProvisionAccountJob`, `ReconcileAccountJob`, `TeardownAccountJob` — resolvem o `ProductProvisioner` do produto e chamam o método certo. Webhooks nunca provisionam síncronos (§8.2 responde < 5s).
- **Command:** `spelt:reconcile` (agendado) — re-hidrata entitlements + chama `onPlanChanged`/`provision` para curar drift de webhook perdido.
- **Enforcement de downgrade = política do produto.** O kit **não** desprovisiona sozinho. No `onPlanChanged`, o produto decide:
  - **WhatsApp** → reconcilia forte (desconecta números acima do novo limite);
  - **Link na Bio** → soft-cap (esconde/bloqueia excedente);
  - respeitando `has_access`/grace do Spelt para o *bloqueio* de acesso (isso o gate já cobre).
- **Trava de downgrade no Spelt (complementar):** para *impedir* um downgrade que violaria o uso atual, o produto reporta a utilização de cada feature ao Spelt (`PUT /seller/external/v1/subscription/{id}/feature-usage` — shipped 2026-08-28, ver `docs/todo/2026-08-28-subscription-feature-usage.md`); o portal Customer bloqueia o downgrade **antes** de acontecer (400). O `onPlanChanged` fica para reconciliar o que passar.
- **Plano-aware:** `provision()`/`onPlanChanged()` recebem os entitlements (§9) — é assim que a infra "comunica com os planos contratados no Spelt".

---

## 11. Modelo de dados leve (tenancy)

```php
// accounts — identidade da conta (= Spelt customer)
Schema::create('accounts', function (Blueprint $t) {
    $t->ulid('id')->primary();
    $t->string('spelt_tenant_id', 26)->unique();          // ponte com o Spelt
    $t->string('name');
    $t->string('status')->default('active');               // active | suspended | cancelled
    $t->string('provisioning_status')->default('pending'); // pending | provisioned | suspended | torn_down
    $t->timestamps();
    $t->softDeletes();
});

// account_subscription — estado de billing + entitlements sincronizados (hasOne por ora)
Schema::create('account_subscription', function (Blueprint $t) {
    $t->id();
    $t->foreignUlid('account_id')->constrained()->cascadeOnDelete();
    $t->unsignedBigInteger('spelt_subscription_id')->nullable()->index();
    $t->string('plan_id', 26)->nullable();
    $t->string('plan_name')->nullable();
    $t->string('plan_slug')->nullable();
    $t->string('status')->default('inactive');             // inactive | active | past_due | cancelled
    $t->boolean('has_access')->default(false);
    $t->json('entitlements')->nullable();                  // snapshot das features do plano (§9)
    $t->timestamp('current_period_end')->nullable();
    $t->timestamp('trial_ends_at')->nullable();
    $t->timestamp('activated_at')->nullable();
    $t->timestamp('cancelled_at')->nullable();
    $t->json('raw')->nullable();
    $t->timestamps();
});

// users — vínculo à conta + ponte com o user do Spelt (sem senha gerida — SSO-only)
Schema::table('users', function (Blueprint $t) {
    $t->foreignUlid('account_id')->nullable()->constrained();
    $t->string('spelt_user_id', 26)->nullable()->index();
});

// spelt_webhook_events — idempotência + auditoria
Schema::create('spelt_webhook_events', function (Blueprint $t) {
    $t->id();
    $t->string('spelt_event_id')->unique();
    $t->string('event');
    $t->json('payload');
    $t->timestamp('received_at');
    $t->timestamp('processed_at')->nullable();
    $t->string('status')->default('received');             // received | processed | failed
});
```

**Isolamento:** `Account hasMany User`; `User belongsTo Account`. Toda query passa por `forCurrentAccount()` — mesmo vetor de vazamento que `tenant_id` no Spelt.
**Progressão para N assinaturas:** `account_subscription` já é tabela separada (não coluna em `accounts`); promover de `hasOne` para `hasMany` não exige reescrever o schema.

---

## 12. Identidade e sessão

- **SSO-only, sem senha.** Conta/usuário nascem via SSO (ou webhook), sem credencial gerida. Após o primeiro SSO, a **sessão local persiste**; expirou → re-entra pelo portal Spelt (SSO). Zero gestão de senha.
- **Extension point opcional:** magic link (passwordless) para retorno direto sem passar pelo Spelt — só se um produto pedir; **não** entra no kit mínimo.
- **Sem cadastro próprio:** toda origem de conta vem do Spelt.

---

## 13. Configuração / contrato de ENV

```env
SPELT_API_URL=https://api.spelt.com.br
SPELT_API_KEY=splt_live_...                 # splt_test_ em dev/CI
SPELT_WEBHOOK_SECRET=...
# (O portal Customer do SSO reverso NÃO é env: vem no payload do SSO como `panel_url` e é
#  gravado em accounts.spelt_panel_url — self-config por Seller.)
PRODUCT_NAME="Webhooks.io"
PRODUCT_LOGO=/img/logo.svg
```
```php
'spelt' => [
    'url'            => env('SPELT_API_URL', 'https://api.spelt.com.br'),
    'api_key'        => env('SPELT_API_KEY'),
    'webhook_secret' => env('SPELT_WEBHOOK_SECRET'),
],
```

---

## 14. Notificações

**Não vivem no kit.** As notificações ao cliente (in-app + e-mail) são responsabilidade do **portal Customer do Spelt**. O kit não tem central de notificações. Se um produto precisar de um alerta operacional próprio (ex.: "sessão WhatsApp caiu"), isso é add-on opt-in — nunca a via padrão para comunicação comercial com o cliente.

---

## 15. Usage-based — opt-in, custo baixo

Fora por padrão, mas **acoplável com pouco custo**:
- `SpeltClient::reportUsage()` já vem no cliente (método ocioso até ser usado);
- recipe documentado de **onde contar** (o produto escolhe o gancho — ex.: cada webhook recebido);
- **idempotência** por `event_id` no report;
- liga por config (`SPELT_USAGE_ENABLED`) + `metric_id`.

Custo real ≈ 1 método + doc + 1 flag. Nenhum metering roda sem o produto pedir — não fere o "kit mínimo".

---

## 16. Segurança

- **Webhook:** valida `Bearer` secret, idempotência por `spelt_event_id`, processa em fila.
- **SSO token:** uso único, 60s, sempre validado server-side.
- **Isolamento:** `forCurrentAccount()` em **toda** query.
- **Segredos:** `.env` fora do git; chaves separadas por ambiente.
- Referência: `docs/security/` do Spelt para os padrões herdados.

---

## 17. Manutenção e testes do Kit (template vivo)

"Template que se copia" **não** significa template abandonado. O *template* é mantido e testado; as *cópias* forkam.

- **Repo próprio versionado** (CHANGELOG + tags git). Cópias fazem cherry-pick manual das correções relevantes (sem sync automático — decisão §2.3).
- **Suíte Pest** (padrão do Spelt, `docs/technical/testing.md`) cobrindo a camada `App\Spelt\*`: SSO receiver, webhook (cada evento → ação), gate, entitlements, jobs de provisionamento (com `ProductProvisioner` fake).
- **Teste de integração contra o Spelt em Test Mode** (`splt_test_`): valida SSO/webhook/`GET /plan` de ponta a ponta **sem cobrança real** — é exatamente o propósito do Test Mode (`docs/technical/test-mode.md`).
- **Smoke test de bootstrap** (CI): copia o template, renomeia, `migrate`, sobe, bate no `/health` → **prova** que "subir um projeto novo" funciona a qualquer momento.

---

## 18. Bootstrap de um novo produto (copy & rename)

1. Copiar `code/starter-kit.spelt.com.br/code/` → `code/<produto>/`, renomear `api/` + `platform/` (+ `www/`, se o produto tiver site).
2. Ajustar `.env` (`SPELT_*`, `PRODUCT_*`), gerar `APP_KEY`, rodar migrations.
3. No **Marketplace de Integrações** do Spelt: criar a integração, configurar Webhook URL (`/spelt/webhook`) + SSO URL (raiz do produto; Spelt anexa `/auth/spelt`), copiar `SPELT_API_KEY` + `SPELT_WEBHOOK_SECRET`.
4. Implementar o `ProductProvisioner` (§10) e o **valor** do produto.

---

## 18.1 Deploy em produção — Coolify (trilha do kit)

> **Decisões:** (2026-08-31) SaaS do kit sobe via **Coolify** self-hosted — NÃO pelo aparato do Spelt
> (Traefik + Watchtower + SOPS + `./deploy`) nem pelo Coolify **Cloud** (o Otávio quer controle total +
> firewall + usar pra outros projetos; apoia o projeto via **GitHub Sponsor**). (2026-09-01) Topologia
> **control plane + workloads**: um Droplet só pro Coolify (**A**) + Droplet(s) pros containers (**B**).
> Spelt e os 14 projetos ficam na trilha atual. Se um SaaS crescer, ganha Droplet próprio (add server no
> Coolify) ou gradua pro aparato Spelt (a imagem é portátil — não prende).

**DEV local é inalterado** (Mutagen + imagem custom `iporto99/php-8-3` + bind-mount seguem idênticos). A
interface com a **produção** é **a imagem no GHCR** (Modelo 2): `push` → **GitHub Actions** builda o
Dockerfile → `ghcr.io/<owner>/<projeto>-<app>` → **`POST /api/v1/deploy`** no Coolify → o workload puxa a
imagem e recria. O build **sai do box** (não pesa no workload; imagem versionada = backup off-VM). No
Coolify a app é do tipo **Docker Image** (não Git+Dockerfile; o GitHub App do Coolify não entra na trilha).

### Artefatos (commitados em CADA repo de app — o Coolify builda deles)

```
<app>/
  Dockerfile            # FROM iporto99/php-8-3 (mesma base do dev) + nginx; serve direto
  .dockerignore         # ⚠️ exclui .env*, vendor, node_modules e bootstrap/cache/* (ver gotcha)
  .deploy/
    nginx.conf          # vhost → root=public, fastcgi 127.0.0.1:9000
    supervisord.conf    # papel WEB: nginx + php-fpm num container
    entrypoint.sh       # perms 1000:1000 + config:cache (após envs do Coolify) + exec CMD
```

A imagem NÃO faz rsync, NÃO embute `.env`, NÃO roda migrate no boot (o oposto do "fat" do Spelt). O
`platform`/`www` = + `npm run build` (as `VITE_*` do kit são só do dev-server; o bundle resolve a API em
runtime). **Um image, três papéis** — como o tipo **Docker Image** do Coolify NÃO expõe "start command",
o papel vem por env **`CONTAINER_ROLE`** (o entrypoint lê e executa):

| Papel | Env no app | Comando efetivo |
|---|---|---|
| Web (default) | *(sem `CONTAINER_ROLE`)* | supervisord → nginx + php-fpm |
| Worker | `CONTAINER_ROLE=worker` | `php artisan horizon` |
| Scheduler | `CONTAINER_ROLE=scheduler` *(ou Scheduled Task nativa `schedule:run`)* | `php artisan schedule:work` |

### Provisionamento + runbook operacional

Os Droplets são provisionados pelo **`install-coolify.sh`** (`~/Environment/Dev/Server/Server/Ubuntu-24/`):
`SERVER_ROLE=coolify` p/ o A, `SERVER_ROLE=worker` p/ o B. Ele NÃO usa ufw (o Coolify o remove → firewall
do host fica na **DigitalOcean Cloud Firewall**) e configura o DOCKER-USER com `--ctorigdstport`.

O **passo a passo completo** — setup do Coolify, MySQL/Redis, os 5 recursos
(api/worker/scheduler/platform/www como Docker Image), blocos de env por app, DNS, secrets do auto-deploy
e a **lista de gotchas** — fica no runbook de CADA deploy: **`deploy.<projeto>.com/docs/coolify-deploy.md`**
(o do Psst é o modelo a copiar).

> **Exemplo validado ponta a ponta (TryPsst, 2026-09-01):** A=`159.65.35.89` (control plane,
> `coolify.iporto.net.br`) + B=`159.203.85.170` (workloads); 5 apps + MySQL + Redis; HTTPS + cert
> Let's Encrypt válido nos 4 domínios; auto-deploy (push→build→GHCR→Coolify recria os 3 apps da api) verde.
> Recriar o produto do zero = seguir o runbook. Gotchas resolvidos ao longo do caminho estão listados nele
> e em [[kit-coolify-deploy-track]].

---

## 19. Roadmap por fases

| Fase | Entregável | Status |
|---|---|---|
| **0** | Esta spec | ✅ consolidada 2026-08-11 |
| **1** | Scaffold do template: API enxuta + Platform shell + camada de integração (SSO, webhook, gate, `SpeltClient`), entitlements, jobs/contract de provisionamento, migrations, `spelt:reconcile`, suíte Pest + smoke test. **Sem produto de exemplo.** | 🔶 **API pronta** (Bloco 1 dados + Bloco 2 comportamento; 15 Pest verdes, 08-28) · Platform pendente |
| **2** | Produto de exemplo mínimo (Webhook.site) provando o fluxo ponta a ponta | ⬜ |
| **3** *(futuro)* | Empacotar a camada de integração como pacote Composer, **se** um dia quiser sync | ⬜ |

---

## 20. O que fica no Spelt (não implementar no kit)

Billing · Planos (CRUD) · Faturas · Pagamentos · Cupons · Wallet · Proration · Tax · Usage **billing** · Gateway + drivers · Fiscal/NF · Suporte/Tickets · Afiliados · Communication · **Notificações ao cliente** · Backoffice · `TenantType`/hierarquia · 4 contextos · Access-grant delegado · Impersonation · `livemode` · Resolução de domínio multi-Seller · External API v1 (o kit é **cliente**).

---

## 21. Dependências no lado do Spelt

| Item | Situação | Bloqueante? |
|---|---|---|
| `GET /plan/{id}` retorna features do plano | **Já existe** (integration-base.md) | Não |
| `has_access` no DTO de subscription + webhooks `access_blocked/restored` | **Já existe** (delinquency-handling.md) | Não |
| `/api/public/sso/validate` (uso único, 60s) | **Já existe** (laravel.md) | Não |
| Enriquecer payload de webhook/SSO com features do plano | **Otimização futura** — evita chamada extra a `GET /plan` | Não |
| `POST /plan-category` aceitar `app_url`, `icon_url`, `show_on_dashboard`, `dashboard_order`, `group_name` + `PUT /plan-category/{id}` | **Feito 2026-09-04** (Fábrica de Lead). Sem os campos de dashboard a categoria nasce inútil: o cliente assina e não tem por onde entrar. O `PUT` é o que torna o provisionamento por API idempotente | **Sim** para provisionar catálogo por API |
| `plan_name`/`plan_slug` no `SubscriptionDTO` dos webhooks | Hoje vem só `plan_id`; o kit contorna buscando o plano no `EntitlementService`. Enviar no payload eliminaria a dependência | Não (contornado) |

---

## 22. Extension points documentados (fora do kit mínimo)

Magic link de retorno (§12) · N assinaturas por conta (§11) · Media/File/Notification operacional (§8, §14) · Usage-based metering (§15) · **Módulo de API-keys do produto** (API própria do produto para seus usuários — herdável do `dev_apis` da api.spelt.com.br, simplificado; validado no MailValidation) · **Módulo de webhook de saída** (entrega + retry + assinatura para o CRM/sistema do cliente — validado na Fábrica de Lead) · **Módulo de provisionamento de domínio/SSL** (Cloudflare for SaaS — validado no LinkTO; o Spelt já usa esse padrão para os domínios custom do Seller, ver `custom-domains.md`). Cada um é acoplável sem reescrever a base.

---

## 22.1 Aprendizados da Fábrica de Lead (2026-09-04)

O 2º produto sobre o kit. O que ele devolveu para o template:

**Dois bugs que quebravam qualquer produto:**

| Bug | Sintoma |
|---|---|
| `SpeltClient::payInvoice` mandava `amount` | O Spelt mudou `/pay` em 2026-09-01 e passou a rejeitar o campo. Resultado: **400 silencioso, fatura pendente, ZERO créditos concedidos** — a compra parecia funcionar. |
| `plan_name`/`plan_slug` nunca preenchiam | O `SubscriptionDTO` do webhook traz só `plan_id`. A assinatura ficava sem plano na tela. Corrigido no `EntitlementService`, que já buscava o plano inteiro para extrair as features. |

**Uma lacuna de provisionamento:** o produto não tinha como criar seu catálogo no Spelt.
Virou o **`dev:spelt-catalog`** — genérico, dirigido por `config/spelt-catalog.php`,
idempotente por slug, com `--dry`. Cria categoria de plano, tipo de crédito, features e
degraus, e vincula tudo. É o passo que antecede o `dev:spelt-purchase`.

> ⚠️ **A categoria de plano é obrigatória na prática.** Sem ela vinculada ao plano, o cliente
> assina, paga, é provisionado — e **não tem por onde entrar no produto**, porque o dashboard
> do portal Customer lista categorias com `show_on_dashboard` e usa `app_url` como link de
> acesso. Falha silenciosa: tudo parece certo até alguém tentar usar.
>
> Exigiu estender a External API do Spelt (`POST /plan-category` não expunha `app_url` nem
> `show_on_dashboard`, e não havia `PUT`). Ver §21.

**Uma dívida de documentação:** nove convenções de tela erradas na primeira tela, todas
existentes nas implementações vivas e nenhuma escrita. Gerou o
[`DESIGN.md`](../../code/platform/DESIGN.md) e os componentes que faltavam
(`x-shared.side-*`, modal com drawer, helpers, sidebar em acordeão).

---

## 23. Aprendizados dos casos de referência (2026-08-11)

Spec validada contra dois produtos reais (briefs em [`docs/starter-kit-ideas/`](../starter-kit-ideas/)), que **bracketam o espectro de cobrança**:

| | Fábrica de Lead | MailValidation |
|---|---|---|
| Cobrança | recorrente + capacidade | pay-as-you-go consumível (compra única) |
| Provisionamento | leve | leve (engine pesada, mas compartilhada) |
| Quota | créditos (leads) | créditos (validações, custo por tier) |

**O que a spec absorveu:**
1. **Credit-sync virou núcleo** (§8.6) — não é mais "extension point". Os dois produtos precisam. Cobre quota recorrente **e** pay-as-you-go pelo mesmo mecanismo, com débito arbitrário (o produto define o custo por operação).
2. **Módulos opcionais confirmados** (§22): API-keys do produto, webhook de saída e provisionamento de domínio/SSL (este validado pelo teste do template com o LinkTO).
3. **Trava de downgrade** resolvida sem migrar a UX de plano para o produto: o produto reporta utilização de feature ao Spelt (`PUT .../subscription/{id}/feature-usage` — shipped 2026-08-28), e o portal Customer bloqueia; o `onPlanChanged` cobre o resto (§10).
4. **Princípio da infra própria** (§3): a maioria dos produtos traz a própria infra (engine de match, pipeline da Receita, pool de IPs de SMTP/TELNET) — o kit é a casca comercial/identidade e **fica fora** dela.
