# CLAUDE.md — starter-kit / API

Guia de contexto para o Claude Code neste ambiente. Leia antes de qualquer tarefa.

---

## O que é

**API** de um novo produto SaaS já plugado ao **Spelt** (Starter Kit). Enxuta por
design (*bridge leve*): traz só a casca Laravel, as convenções e a **camada de
integração com o Spelt**. O comercial — billing, planos, cobrança, fatura, suporte,
notificações — **fica no Spelt**; este projeto é **cliente** da External API v1 do
Spelt e **recebe webhooks** dele.

> Spec canônica: `docs/technical/starter-kit.md` no repo `api.spelt.com.br`.
> Briefs de produtos de referência + o TEMPLATE de brief: `docs/starter-kit-ideas/`.

## Ambiente

Roda **exclusivamente em Docker**. **Nunca execute na máquina hospedeira** `composer`,
`artisan`, `npm`, `php` — só **dentro do container**.

**Container de DEV** (existe só em desenvolvimento, **nunca em produção**):
- Container: `spelt-php-api-kit` · contexto Docker: `vm-docker-code`
- Caminho da app no container: `/var/www/html/api.spelt.dev`
- Padrão de execução (agente tem acesso a este container de teste):
  ```bash
  docker --context vm-docker-code exec -w /var/www/html/api.spelt.dev spelt-php-api-kit <cmd>
  # ex.: ... php artisan migrate   |   ... php artisan test
  ```

> O Platform terá o seu próprio container de DEV (a ser criado quando aquela fase chegar).

### Boot do container — `init-laravel.sh`

Convenção comum a todos os projetos do kit: um `init-laravel.sh` na raiz de cada app,
rodado pelo `command:` do compose a cada subida. **Idempotente** — cria storage dirs, ajusta
permissões (775), copia `.env` do `.env.example` se faltar, `composer install`, `key:generate`
se sem `APP_KEY`, limpa caches, `storage:link` e `npm install` (só se houver `package.json`).
É o **mesmo arquivo** na API e no Platform.

```yaml
# API:      command: sh -c "chown 1000:1000 <app> -R && cd <app> && ./init-laravel.sh && tail -f /dev/null"
# Platform: command: sh -c "chown 1000:1000 <app> -R && cd <app> && ./init-laravel.sh && npm run dev -- --host 0.0.0.0"
```

> **Não roda `migrate`** (evita a armadilha "migrate a cada boot" do deploy). Rode uma vez
> após o primeiro boot: `php artisan migrate`.

---

## Stack
- Laravel 12 / PHP 8.2 · MySQL · Redis (cache/fila/sessão)
- Auth: **Laravel Sanctum**, guard único `api`. **SSO-only** — os usuários nascem do
  handoff do Spelt; o kit **não gere senha** (ver spec §12).

## Convenção de namespaces — **o Model manda o domínio**

Namespaces "ricos" que explicam a função, no padrão do Spelt. **O Model define o
domínio; Controllers, Services, Requests, Jobs e traits seguem a MESMA cadência.**

| Domínio | Model | Onde vivem os companheiros |
|---|---|---|
| `Core\Account` | `Models\Core\Account\{Account,User,PersonalAccessToken}` | `Services\Core\Account\*`, `Http\Controllers\Core\Account\*`, `Traits\Core\Account\*` |
| `Billing` | `Models\Billing\{Subscription,CreditLedger}` | `Services\Billing\*` (CreditService, EntitlementService…), `Http\Controllers\Billing\*` |
| `Spelt` (integração) | `Models\Spelt\WebhookEvent` | `Services\Spelt\*` (SpeltClient, WebhookProcessor, AccountService…), `Http\Controllers\Spelt\*` |

- **Tabelas prefixadas por domínio**: `core_accounts`, `core_account_users`,
  `billing_subscriptions`, `billing_credit_ledger`, `spelt_webhook_events`.
- **Rotas** em `routes/api/{Domínio}/{Model}Route.php` (auto-carregadas pelo
  `RouteServiceProvider`, grupo `api` + prefixo `/api`); fallback em `routes/api/Api.php`.
  Cada arquivo declara só o middleware adicional (`auth:api`, `spelt.webhook`, …).
- **Migrations** em `database/migrations/{Domínio}/…` (loader recursivo no `AppServiceProvider`).
- **Contratos** que o produto implementa: `App\Contracts\*` (ex.: `ProductProvisioner`),
  como o `App\Contracts\Storage\BucketInterface` do Spelt.
- Ao criar QUALQUER classe nova, primeiro decida o domínio do Model; o resto segue.

## Tenancy leve — `account_id` é o `tenant_id` daqui

`Account` = um **Tenant (customer) do Spelt espelhado** (`spelt_tenant_id`). Todo dado
do produto pertence a uma Account; o isolamento é por `account_id`.

- Leitura: `Models\...\Scope::forCurrentAccount()` em **TODA** query de dado do produto.
- Escrita: `Traits\Core\Account\Authorization::injectAccountId()` / `ensureAccountOwnership()`.
- Esquecer o scope é o mesmo vetor de vazamento entre contas que esquecer o `tenant_id`
  no Spelt.

## Camada de integração com o Spelt (o núcleo)

- **`Services\Spelt\SpeltClient`** — cliente da External API v1 (getPlan, getSubscription,
  reportUsage, generateReverseSso…). *(Bloco 2)*
- **Webhook receiver** — `POST /api/spelt/webhook`, valida o Bearer secret, idempotente
  por `spelt_event_id` (`Models\Spelt\WebhookEvent`), enfileira. *(Bloco 2)*
- **`Models\Billing\Subscription`** — espelho do estado de billing + snapshot de
  **entitlements** (`entitlement()`, `allows()`, `withinLimit()` na `Account`). Spec §9.
- **`Models\Billing\CreditLedger`** — **crédito é o módulo de Créditos do Spelt** (não é
  primitivo novo): saldo = SUM(amount), alimentado por `credit.granted`/`credit.expired`;
  o consumo é do produto. Spec §8.6.
- **`App\Contracts\ProductProvisioner`** — ganchos `provision`/`onPlanChanged`/`teardown`
  (por fila). Padrão = `Services\Spelt\NullProductProvisioner` (no-op). Spec §10.

### Comandos de dev — conta local vs. compra real no Spelt

| Comando | O que faz | Quando usar |
|---|---|---|
| `dev:token` | Cria conta+assinatura+créditos **só no produto** (local) e imprime um token. NÃO existe no Spelt. | Testar o Platform isolado, sem o Spelt. |
| `dev:spelt-purchase {plan_id?}` | Cria customer + usuário + assinatura no **Spelt** e **quita a fatura** (test mode) → ativa + concede os créditos do plano + dispara os webhooks. | Testar o **portal Customer do Spelt** de verdade (assinatura/créditos reais). |

`dev:spelt-purchase` exige `SPELT_API_URL` + `SPELT_API_KEY` (use `splt_test_`). Sem `plan_id`,
lista os planos e pergunta. A quitação é via `POST /invoice/{id}/pay` (Payment COMPLETED sem
dinheiro real). **Gotcha:** o `GET /invoice/{id}` da External API pode estar quebrado (relação
`items` inexistente no Spelt) — o comando cai no `total_price` da assinatura como valor da
quitação, então não depende dele.

## IA (LLM) — camada genérica (`App\Services\Ai`)

Chat/completion agnóstico de fornecedor. O código **nunca escolhe o modelo na chamada** — escolhe um **perfil** semântico e o `AiProvider` roteia para o driver certo (`config/ai.php`). Trocar OpenRouter → Anthropic/OpenAI direto = novo driver, sem tocar no chamador. Mesmo padrão do Gateway do Spelt (Contract + Drivers + Resolver + DTO).

```php
use App\Services\Ai\AiProvider;
use App\Services\Ai\DTO\AiRequest;

public function __construct(private readonly AiProvider $ai) {}

$res = $this->ai->driver('creative')->chat(
    AiRequest::make()->system('...')->user('...')->asJson()->maxTokens(2000)
);
$res->text;              // conteúdo
$res->json();            // decodifica (chamadas ->asJson())
$res->usage->totalTokens;
```

- `AiRequest` é fluente e **não tem model** (`system()/user()/assistant()/temperature()/maxTokens()/asJson()`).
- `AiResponse`: `text`, `model`, `usage` (tokens — útil p/ o ledger de créditos), `finishReason`, `raw`, `json()`.
- Perfis (`config/ai.php`): `default` / `fast` / `creative` → cada um mapeia `driver`+`model`+params. Omitir o perfil usa `default`. Injetar `AiDriver` (em vez de `AiProvider`) dá o driver do perfil default.
- Falha (config/rede/parse) → `AiException` — o chamador decide o fallback.

**Config/ENV** (só `OPENROUTER_API_KEY` é obrigatória): `OPENROUTER_API_KEY` · `AI_MODEL`/`AI_MODEL_FAST`/`AI_MODEL_CREATIVE` · `AI_DRIVER` (default `openrouter`) · `OPENROUTER_BASE_URL`/`OPENROUTER_REFERER`/`OPENROUTER_TITLE` · `AI_TIMEOUT` · `AI_PROFILE`.

**Adicionar um fornecedor** (ex.: Anthropic direto): novo `Drivers\AnthropicDriver implements AiDriver` + entrada em `ai.drivers` + `case` no `AiProvider::driver()`. O chamador não muda.

**Log de uso (auditoria de custo — padrão do kit):** passe um `AiUsageContext` para `chat()` e o `AiUsageLogger` grava uma linha em **`ai_usage_logs`** (`App\Models\Ai\AiUsageLog`): tokens + custo USD (de `config('ai.pricing')`, por 1M tokens) + quem (`account_id`/`user_id`) + para quê (`action` + assunto polimórfico). À prova de falha (um erro de log nunca quebra a chamada). O caminho cru `driver()->chat()` NÃO loga.

```php
$ai->chat($request, 'creative', new AiUsageContext(
    action: 'studio.generation', accountId: $account->id,
    subjectType: $gen->getMorphClass(), subjectId: (string) $gen->getKey(),
));
```

**Testes:** perfil `fake` → `FakeDriver` (determinístico, sem rede) no caminho feliz; `Http::fake()` p/ exercitar o `OpenRouterDriver` (payload/headers/parse) e o log de uso. Ver `tests/Unit/Ai/` e `tests/Feature/Ai/AiUsageLogTest.php`.

> ⚠️ **Slug de modelo do OpenRouter:** confira o slug EXATO no OpenRouter — um inválido dá **HTTP 404 "No endpoints found"** (≠ chave inválida, que dá 401). Válidos hoje: `anthropic/claude-sonnet-4`, `openai/gpt-4o-mini`. `anthropic/claude-3.5-sonnet` está morto.

## Jobs — fila e connection SEMPRE no `__construct`

Aprendizado do Spelt (ref.: `api.spelt.com.br/app/Jobs/Webhook/DeliverWebhookJob.php`):
pinar `onConnection`/`onQueue` **no construtor**. Os métodos `queue()`/`connection()`
**não** são hooks do Laravel — definir por lá vira código morto e o job cai no `default`
em silêncio.

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MeuJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(/* ... */)
    {
        $this->onConnection('redis');
        $this->onQueue(env('QUEUE_MEU_DOMINIO', 'default')); // genérico → 'default'
    }
}
```

- Fila de domínio via `env('QUEUE_X', 'default')` — o worker **precisa observar** essa fila;
  jobs genéricos usam `'default'` literal.
- **Gotcha de teste:** `onConnection('redis')` sobrescreve o `QUEUE_CONNECTION=sync` do
  phpunit. Por isso `TestCase::setUp()` força `queue.connections.redis.driver = 'sync'`,
  para o job pinado em `redis` rodar inline.

### O worker — Horizon no container `console`

Job só roda se um worker observar a fila. O kit já traz Horizon (`laravel/horizon`) sob
Supervisor, para o container `console` do deploy:

- **`.supervisord/Horizon.conf`** — programa do Supervisor que roda `php artisan horizon`.
  O `supervisord.conf` do deploy tem `[include] files = <app>/.supervisord/*.conf`, então
  todo `*.conf` daqui é carregado. ⚠️ O caminho dentro do `Horizon.conf` é específico do
  projeto — troque `api.spelt.kit` pelo diretório do seu app (mesmo find/replace do nome).
- **`config/horizon.php`** — `defaults.supervisor-1.queue` lista as filas observadas.
  **Some cada fila nova aqui** (senão o job é enfileirado mas nunca consumido). Já inclui
  `default` + `env('QUEUE_WEBHOOK')`.
- **Invariante de timeout:** `job.timeout` < timeout do supervisor Horizon (150) <
  `queue.connections.redis.retry_after` (180, em `config/queue.php`). Se um job passar do
  `retry_after` ainda rodando, o Redis o reenfileira e ele roda em **duplicidade** — mantenha
  o job idempotente e respeite a cascata.
- **CRON:** o mesmo container roda `schedule:run` via `.docker/Console/cron.d/crontab` no deploy.

Diagnóstico: `supervisorctl status`; log em `storage/logs/horizon.log`; `php artisan
horizon:status`. `Command "horizon" is not defined` = pacote não instalado (rode `composer install`).

**Gate do dashboard (`/horizon`):** protegido por **HTTP Basic Auth** (`HorizonBasicAuthMiddleware`,
anexado em `config/horizon.php`), não pelo gate `viewHorizon` por e-mail — a API é stateless por
token, sem sessão de operador, e o gate padrão deixa a rota **aberta em `local`**. Credenciais em
`HORIZON_BASIC_AUTH_USERNAME/PASSWORD`; **sem elas o dashboard fica trancado (401)**, fail-safe.
Padrão herdado do Spelt.

## Ray (myray.app) — depurador visual (dev)

`spatie/laravel-ray` (require-dev) + `config/ray.php`. O container roda na VM, então
`RAY_HOST` (no `.env`) é o **IP do Mac na LAN**, não `localhost`. Sem `RAY_HOST` cai em
`localhost` (inócuo). Use `ray($var)` no código para inspecionar; desligue em CI com
`RAY_HOST=` vazio ou `RAY_ENABLED=false`.

## Padrão de resposta JSON (herdado do Spelt)

| Situação | HTTP | Formato |
|---|---|---|
| Listagem / item | 200/201 | `{"data": ...}` |
| Regra de negócio violada | 400 | `{"message": "..."}` |
| Validação | 422 | `{"message": "...", "errors": {...}}` |

Use os helpers da `Http\Controllers\Controller` (`ok()`, `fail()`).

## CRUD e rotas — convenções (herdadas do Spelt)

> Ref.: `api.spelt.com.br/docs/technical/crud.md` + `data-access-patterns.md`.

Quando o produto construir CRUDs sobre o kit:
- **Regex ULID na rota** para models com ULID (`Account`, `User`):
  `->where(['id' => '(?i)[0123456789ABCDEFGHJKMNPQRSTVWXYZ]{26}'])`.
  (`Billing\Subscription`/`Billing\CreditLedger` são auto-increment — não aplicar.)
- **Isolamento SEMPRE**: `forCurrentAccount()` em TODA consulta; `injectAccountId()`
  antes do `create()`. Nunca `where('account_id', $manual)` onde o scope existe —
  omitir é o principal vetor de vazamento entre contas.
- **Update = patch parcial** (valida/atualiza só os campos enviados; omitidos mantêm o valor).
- **Respostas** via helpers da `Controller`: `ok()` (200), `created()` (201),
  `fail()` (400, `{message}`), `notFound()` (404, `{error}`); validação → 422 automático.

## Config vs env — `env()` só em `config/`

Fora de `config/` (models, services, jobs de lógica), leia `config()`, nunca `env()`:
com `config:cache` o `.env` não é lido e `env()` cai no default. Ex.: o TTL do token de
sessão vive em `config('services.spelt.session_ttl_days')`.
*(Exceção replicada do Spelt: o nome da fila nos Jobs usa `env('QUEUE_X','default')` — é
variável de ambiente do container, não valor de config.)*

## Enums — padrão de dados (nunca constantes)

Todo campo de **status/tipo** é um **backed enum** em `app/Enums/{Status,Type}/{Domínio}/`
(ex.: `Status\Billing\SubscriptionStatus`, `Type\Billing\CreditType`), com **cast no model**
(`'status' => SubscriptionStatus::class`). Assim o valor **viaja tipado** por
Service/Controller/JSON (serializa para o `value` automaticamente) e o `match` fica
exaustivo — o que constante de classe NÃO entrega.

- **Validação** (ao criar FormRequests): `Rule::enum(SubscriptionStatus::class)`.
- **Ingestão de valor externo** (ex.: status vindo da API do Spelt no reconcile): use
  `SubscriptionStatus::tryFrom($v)` — enum é estrito; `from()` estoura em valor desconhecido.

## Exportação — a máquina está pronta, faltam os tipos

Exportar é assíncrono: o pedido vira uma LINHA em `exports`, o job gera o arquivo, e a tela
acompanha até "Pronto". Sem a linha, o cliente clica, nada acontece na tela, e ele clica de
novo — três vezes, gerando três varreduras da mesma base.

Para o produto exportar alguma coisa, são **dois passos**:

```php
// 1. app/Services/Export/Exporters/LeadExporter.php
class LeadExporter extends Exporter
{
    public function headings(): array { return ['CNPJ', 'Empresa', 'Pontuação']; }

    public function query(): Builder
    {
        // ⚠️ Sem forCurrentAccount(): o job roda fora de uma requisição.
        return Lead::query()
            ->where('account_id', $this->accountId())
            ->when($this->hasFilter('status'), fn ($q) => $q->where('status', $this->filter('status')))
            ->orderBy('id');   // chunk sem ordem estável repete ou pula linha
    }

    public function map(Model $row): array { return [$row->cnpj, $row->company_name, $row->score]; }
}

// 2. app/Services/Export/ExportCatalog.php
'lead' => ['label' => 'Leads', 'exporter' => LeadExporter::class,
           'formats' => self::FORMATS, 'ttl_days' => 7],
```

Não há passo 3: fila, arquivo (CSV e XLSX por streaming), prazo, expurgo, download autenticado
e a tela de Exportações já funcionam. Na Platform, o botão é
`<x-shared.export-button type="lead" filters="exportFilters()" />`.

### O que o kit já decidiu por você

| Decisão | Por quê |
|---|---|
| Um pedido em aberto por tipo, por conta | Exportar varre a base. Três cliques em "não aconteceu nada" seriam três varreduras competindo pela mesma fila. |
| `expires_at` no PEDIDO, renovado na conclusão | A exportação que falha nunca chegaria à conclusão — e ficaria no histórico para sempre. O prazo conta de quando o arquivo passou a existir. |
| `export:purge` diário, também para arquivo órfão | Um CSV de leads é dado pessoal de terceiros parado em disco. Linha apagada à mão ou banco restaurado deixam arquivo que consulta nenhuma encontraria. |
| Download pelo controller, disco privado | Guardar a URL pública do bucket na linha significa que qualquer um com o link baixa a base de outra conta, para sempre, sem sessão. |
| Transição por atribuição direta, não `update()` | Mass assignment respeita o `$fillable`, e campo esquecido lá some **em silêncio**. Foi assim que, num produto que serviu de modelo, toda exportação que falhava perdia a mensagem de erro. |
| Job despachado por `ExportService`, não pelo `boot()` do model | `created` disparando job é invisível: quem lê o controller não vê que ali começa trabalho, e um seed exporta sem querer. |

> ⚠️ **Permissão do disco `exports`.** O arquivo é escrito pelo worker da fila e lido pelo
> PHP-FPM — processos de usuários DIFERENTES. Com o padrão do Flysystem a pasta nasce `0700` e
> pertence a quem a criou: o FPM não consegue nem entrar, `exists()` responde false, e a tela
> mostra "Pronto" para sempre sem oferecer o download. `config/filesystems.php` fixa
> `0755`/`0644` — e isso não torna nada público, porque não há rota servindo a pasta.
>
> Nenhum teste pega isso: `Storage::fake` roda no mesmo processo e com o mesmo usuário. O que
> a suíte trava é a configuração.

---

## Estado da execução (Fase 1 — API completa)

- ✅ **Bloco 1**: esqueleto + camada de dados (models com docblocks ricos, migrations,
  factories, `Scope`/`Authorization`, `ProductProvisioner`).
- ✅ **Bloco 2**: `Services\Spelt\{SpeltClient,WebhookProcessor}` + `Services\Billing\{CreditService,EntitlementService}`
  + `Services\Core\Account\AccountService`, `Jobs\Spelt\ProcessWebhookJob`, controllers
  `Spelt\{WebhookController,SsoController}`, middleware `EnsureSpeltAccess` +
  `VerifySpeltWebhookSecret`, rotas `/spelt/webhook` + `/spelt/sso` + grupo `spelt.access`,
  command `spelt:reconcile` (hourly). **18 testes Pest verdes.**
- ⬜ **Próximo**: o **Platform** (front que consome esta API).

Rodar a suíte: `docker --context vm-docker-code exec -w /var/www/html/api.spelt.dev spelt-php-api-kit php artisan test`
