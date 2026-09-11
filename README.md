<h1 align="center">Starter Kit</h1>

<p align="center">
  <strong>Um SaaS em Laravel no ar em dez minutos —<br>
  login, assinatura, créditos e webhooks já resolvidos.</strong>
</p>

<p align="center">
  <img alt="Laravel 12" src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white">
  <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white">
  <img alt="Docker Compose v2" src="https://img.shields.io/badge/Docker%20Compose-v2-2496ED?logo=docker&logoColor=white">
  <a href="#subir-em-dez-minutos"><img alt="Começar em dez minutos" src="https://img.shields.io/badge/come%C3%A7ar-em%2010%20min-2ea44f"></a>
  <a href="LICENSE"><img alt="Licença MIT" src="https://img.shields.io/badge/licen%C3%A7a-MIT-blue"></a>
</p>

<!-- CAPTURA DE TELA — descomente as 3 linhas abaixo depois de colocar o arquivo.
     O que capturar está em docs/assets/README.md.
<p align="center">
  <img src="docs/assets/painel.png" alt="O painel do Platform rodando em platform.acme.test" width="820">
</p>
-->

Um ponto de partida para subir um SaaS: **API + Platform** em Laravel, com
autenticação, assinatura, créditos e webhooks já resolvidos — e um ambiente de
desenvolvimento que sobe com dois comandos.

A cobrança e o controle de acesso são delegados ao **[Spelt](https://spelt.com.br)**:
o kit já traz o SSO, o recebimento de webhooks e o modelo de créditos e planos.
Para colocar um produto no ar de verdade você precisa de uma conta lá — mas
**não para desenvolver**, como o passo 9 mostra.

Não é um framework nem uma biblioteca. É um **repositório para copiar**: você
gera o seu produto a partir dele e o código passa a ser seu.

---

## Índice

- [O que vem pronto](#o-que-vem-pronto)
- [O que você não vai construir](#o-que-você-não-vai-construir)
- [Requisitos](#requisitos)
- [Subir em dez minutos](#subir-em-dez-minutos)
  - [O caminho curto: o assistente](#o-caminho-curto-o-assistente)
  - [0. Os três caminhos](#0-os-três-caminhos) · [1. Clonar](#1-clonar-a-biblioteca-e-o-kit) · [2. Conferir a máquina](#2-confira-se-a-máquina-dá-conta) · [3. Base compartilhada](#3-a-base-compartilhada--uma-vez-por-máquina)
  - [4. Banco](#4-o-banco-do-seu-produto) · [5. Gerar o produto](#5-gerar-o-produto) · [6. Domínios](#6-resolver-os-domínios) · [7. Conferir](#7-conferir-antes-de-subir) · [8. Subir](#8-subir) · [9. Migrar e entrar](#9-migrar-e-entrar)
- [Quatro repositórios, dois momentos](#quatro-repositórios-dois-momentos)
- [Depois de subir: construir o produto](#depois-de-subir-construir-o-produto)
- [Produção](#produção)
- [Comandos úteis](#comandos-úteis)
- [Documentação](#documentação)
- [Licença](#licença)

---

## O que vem pronto

| | |
|---|---|
| `code/api` | API Laravel — contas, assinaturas, créditos, webhooks, tokens |
| `code/platform` | Painel do usuário — Laravel + Vite |
| `code/www` | Site institucional (opcional) |
| Artefatos de build | Ficam no `deploy.<projeto>`, não nos apps — os apps só têm o workflow que avisa |
| Ambiente de dev | gerado pela biblioteca de deploy, com nginx, PHP-FPM, banco e mail |

---

## O que você não vai construir

Todo SaaS precisa das mesmas coisas em volta do produto: plano, assinatura,
cobrança, fatura, inadimplência, upgrade, área do cliente, webhook, métrica de
receita. **Nada disso diferencia o seu produto — e tudo isso é obrigatório.**

É exatamente a parte que a categoria deixa para você. O padrão dos kits de SaaS
é entregar o gateway e os medidores de uso, e devolver a regra de negócio: o que
cada plano libera, quanto de crédito sobrou, o que acontece num downgrade.

Aqui essa regra já está escrita — e o que fica fora do seu código é operado pelo
[Spelt](https://spelt.com.br).

### Já resolvido no código do kit

| | Onde | O que resolve |
|---|---|---|
| **Entrada por SSO** | `Spelt\SsoController` | O usuário entra vindo do Spelt. O produto não guarda senha, não faz "esqueci minha senha", não faz verificação de e-mail. |
| **Webhook confiável** | `Spelt\WebhookController` · `VerifySpeltWebhookSecret` · `ProcessWebhookJob` | Assinatura verificada, evento gravado, processamento em fila e **idempotente** — reentrega não cobra nem provisiona duas vezes. |
| **Porta de acesso** | `EnsureSpeltAccess` | Assinatura vencida ou cancelada para de abrir o produto, sem `if` espalhado pelas controllers. |
| **Estado da assinatura** | `Billing\Subscription` · `SubscriptionSync` | O plano vigente espelhado localmente, para você consultar sem chamar a API a cada request. |
| **Saldo de créditos** | `Billing\CreditLedger` | Ledger de consumo — débito, crédito e saldo. É o modelo que mais dá trabalho de acertar e o que mais some dos kits. |
| **Reconciliação** | `spelt:reconcile` | Um webhook se perde. Este comando compara com a fonte e conserta, em vez de deixar o cliente com acesso errado. |
| **Cliente da API** | `SpeltClient` | Chamadas servidor-a-servidor já autenticadas. |
| **Volta ao portal** | `Spelt\PortalController` | O usuário vai ver fatura e trocar de plano no portal do Spelt, com a sua marca, e volta. |
| **Provisionamento** | `ProductProvisioner` | O ponto onde *o seu produto* entra: o que criar quando alguém assina, o que desligar quando cancela. É o hook que você implementa. |
| **Desenvolver sem conta** | `dev:token` · `dev:spelt-catalog` | Conta, assinatura e login funcionando na sua máquina, sem cadastro em lugar nenhum. |

### Operado pelo Spelt, fora do seu código

Planos e preços · checkout · faturas · pagamentos e gateways · cupons ·
inadimplência · upgrade e downgrade com prorrateio · área do cliente
white-label · suporte · métricas de receita · fiscal.

Você não implementa, não mantém e não corrige nada disso. A conta só é
necessária para **cobrar de verdade** — o passo 9 mostra o ambiente inteiro
funcionando sem ela.

### O que continua sendo seu

O produto. E o código: o kit não é dependência, é um repositório que você copia.

> [!NOTE]
> **O kit nasce acoplado ao Spelt** — é essa a proposta, e é de onde vem o que
> está na primeira tabela. A camada de integração fica isolada nos namespaces
> `Spelt\*`, então trocá-la é possível; mas se você não quer o Spelt, um kit
> genérico de SaaS provavelmente serve melhor.

---

## Requisitos

| | Mínimo | Observação |
|---|---|---|
| Docker | 20.10+ | com `docker compose` v2 |
| Disco | **20 GB livres** | medido: ~11 GB em uso com tudo no ar |
| RAM | 4 GB | medido: ~1 GB em uso |
| `git`, `curl` | — | |

Funciona em Linux e macOS. Em Apple Silicon, as imagens de terceiros rodam
emuladas — mais lentas, porém estáveis.

> [!IMPORTANT]
> **Disco é o que mais reprova.** Uma VM recém-criada costuma vir com menos
> espaço do que o disco tem de verdade. O `deploy-doctor` do passo 2 mede antes
> de você instalar qualquer coisa.

---

## Subir em dez minutos

### O caminho curto: o assistente

```bash
git clone https://github.com/iporto/deploy.git ~/.deploy-scripts
~/.deploy-scripts/deploy-wizard
```

Ele pergunta o domínio do seu produto, detecta o que já existe na máquina e faz o
resto — mostrando cada comando antes de executar. Se preferir entender o que está
acontecendo, ou se algo falhar no meio, siga os passos abaixo: **são exatamente
os mesmos comandos.**

---

### 0. Os três caminhos

Defina onde cada coisa vai morar. **Todos os comandos deste guia usam estas
variáveis** — assim você não precisa adaptar caminho nenhum no meio do caminho.

```bash
PATH_DEPLOY_SCRIPTS=~/.deploy-scripts    # a biblioteca de deploy (ferramenta)
PATH_KIT=~/starter-kit                   # este repositório (o molde)
PATH_PROJECT=~/deploy.acme.com           # o seu produto (o que você vai construir)
```

> [!IMPORTANT]
> **Elas valem só nesta sessão do terminal.** O processo leva alguns minutos;
> se você abrir outra aba, redefina as três antes de continuar — os comandos
> seguintes dependem delas.

E troque **`acme`** pelo nome do seu produto, em `PATH_PROJECT` e nos comandos
adiante. O domínio determina o nome dos containers (`acme-php-api`), o diretório
dos apps (`code/api.acme.com`) e as linhas do `/etc/hosts`. Trocar pela metade
gera erros que não apontam para a causa.

### 1. Clonar a biblioteca e o kit

```bash
git clone https://github.com/iporto/deploy.git "$PATH_DEPLOY_SCRIPTS"
git clone https://github.com/Codijo/starter-kit.spelt.com.br.git "$PATH_KIT"

export PATH="$PATH_DEPLOY_SCRIPTS:$PATH"    # ponha no seu .bashrc/.zshrc
```

> [!TIP]
> **Em equipe, fixe a versão da biblioteca.** O clone acima segue a `main` — duas
> pessoas que clonam com uma semana de diferença rodam código diferente. Os
> [releases](https://github.com/iporto/deploy/releases) existem para isso:
>
> ```bash
> git clone --branch v1.0.0 --depth 1 \
>   https://github.com/iporto/deploy.git "$PATH_DEPLOY_SCRIPTS"
> ```

> [!TIP]
> Se já clonou algum dos dois, só ajuste a variável correspondente no passo 0 e
> pule o `git clone` dele.

### 2. Confira se a máquina dá conta

```bash
deploy-doctor
```

Verifica Docker, disco, RAM e portas **antes** de você instalar qualquer coisa.
Se houver bloqueio, ele diz qual e como resolver — melhor descobrir agora do que
no meio do caminho.

### 3. A base compartilhada — uma vez por máquina

```bash
deploy-infra up
```

Na primeira execução ele cria o `$PATH_DEPLOY_SCRIPTS/infra/.env.dev` e pede duas
senhas. Gere com `openssl rand -hex 24` — o próprio arquivo explica. Rode de novo e sobem Traefik,
MariaDB, Redis e Mailpit, na rede `shared`.

### 4. O banco do seu produto

```bash
deploy-infra createdb acme --print-env > /tmp/acme-db.env
```

Cria database, usuário e senha. Você não abre cliente SQL nenhum.

### 5. Gerar o produto

```bash
deploy-scaffold-project acme.com \
  --from-kit "$PATH_KIT" \
  --dir "$PATH_PROJECT" \
  --db-env /tmp/acme-db.env \
  --apply
```

O `--dir` é o que garante que o projeto nasça onde você decidiu, e não no
diretório em que por acaso você estava.

Isso cria o projeto com os apps já renomeados (`code/api.acme.com`,
`code/platform.acme.com`), os envs apontando para a infra, e o ambiente de dev.

### 6. Resolver os domínios

O comando imprime as linhas. Acrescente ao `/etc/hosts` **da máquina onde roda o
navegador**:

```
127.0.0.1 acme.test www.acme.test
127.0.0.1 api.acme.test
127.0.0.1 platform.acme.test
```

> [!WARNING]
> Sem isso você **não toma erro** — `.test` não resolve e o navegador tenta
> uma busca ou um host aleatório. O sintoma não aponta para a causa.

### 7. Conferir antes de subir

```bash
deploy-doctor "$PATH_PROJECT"
```

Agora com o projeto, ele verifica o que o passo anterior pediu: se os domínios
resolvem e se o `DB_HOST` dos apps aponta para a infra. É a checagem que pega o
erro mais confuso do fluxo — sem a linha no `hosts` **o navegador não dá erro**.

### 8. Subir

```bash
cd "$PATH_PROJECT"
./deploy-run dev
```

> [!NOTE]
> **A primeira subida leva alguns minutos.** Cada app roda `composer install`
> e o Platform ainda faz `npm install`. Enquanto isso, `/login` já responde mas o
> painel devolve **500 com "Vite manifest not found"** — é o servidor do Vite
> ainda subindo. Espere o arquivo `code/platform.acme.com/public/hot` aparecer.

### 9. Migrar e entrar

> [!WARNING]
> **Espere o `composer install` terminar.** Rodar o `migrate` antes falha com
> um erro sobre `vendor/autoload.php` que não sugere "aguarde". Para saber se já
> pode:
>
> ```bash
> deploy-doctor "$PATH_PROJECT"     # diz se as dependências estão instaladas
> ```

```bash
docker exec acme-php-api sh -c 'cd api.acme.com && php artisan migrate --force'
docker exec acme-php-api sh -c 'cd api.acme.com && php artisan dev:token'
```

> [!TIP]
> O nome do container é `<slug>-php-api` e o app fica em `api.<seu-dominio>` dentro
> dele. O `deploy-scaffold-project` imprime estes dois comandos já preenchidos ao
> terminar — copie de lá.

O `dev:token` cria conta, assinatura e usuário, e imprime um link pronto:

```
http://platform.acme.test/auth/dev-login?token=…
```

Abra e você está no painel. **Sem conta no Spelt** — é o caminho de
desenvolvimento, e ele só existe fora de produção.

---

## Quatro repositórios, dois momentos

O produto que nasce daqui **não é um repositório só**. É uma árvore:

```
deploy.acme.com/          infraestrutura — compose, nginx, envs, hosts
└── code/
    ├── api.acme.com/     repositório próprio
    ├── platform.acme.com/  repositório próprio
    └── www.acme.com/     repositório próprio
```

O `.gitignore` do projeto ignora `/code/*` **de propósito**: os apps não moram
dentro do repositório de deploy. Cada um tem o seu repositório.

**Quem constrói as imagens é o repositório de deploy**, que guarda os
Dockerfiles em `.docker/Code/<app>/`. O repositório do app não builda: ele
versiona o commit e avisa. Um Dockerfile muda todos os apps num commit só, e o
contexto de build pode ser inspecionado antes do push — é o que permite a
varredura de segredo.

O `deploy-scaffold-project` já entrega tudo isso iniciado: `git init` e primeiro
commit em cada caixa. Você só cria os repositórios remotos e dá `push`.

> [!WARNING]
> **Não commite os apps dentro do repositório de deploy.** Parece funcionar e
> falha calado: o `/code/*` deixa o código de fora do commit, quem clonar recebe
> um `code/` vazio e nenhum erro aparece.

### Momento 1 — você monta o projeto

É o quickstart acima. O assistente ou os nove passos geram a árvore, e o
scaffold imprime, ao terminar, o comando de `remote add` + `push` de cada caixa.

### Momento 2 — alguém entra no projeto andando

Quem chega depois **não usa o kit**. O caminho é outro: clonar o repositório de
deploy, criar `code/` e clonar dentro dele cada app a que tem acesso — o
ambiente sobe com um subconjunto.

O scaffold gera esse runbook já preenchido com os nomes reais do seu produto,
dentro do próprio projeto:

```bash
cat deploy.acme.com/docs/onboarding.md
```

E o `deploy-doctor` reconhece essa situação: `code/` vazio vira erro apontando
para o onboarding, em vez de deixar o ambiente subir com diretórios vazios no
lugar dos apps.

---

## Depois de subir: construir o produto

O ambiente no ar é o começo. O passo seguinte é desenhar o que você vai fazer:

1. Duplique `docs/TEMPLATE.md` — o scaffold já o copiou para `deploy.acme.com/docs/`.
2. Descreva o produto: o que ele faz, o que cobra, o que limita.
3. **É aí que aparecem as necessidades reais** — quais planos, quais créditos,
   quais features. E é aí que você cria a conta no Spelt e configura o catálogo
   (`php artisan dev:spelt-catalog`).

Até esse ponto, nada de credencial externa é necessário.

---

## Produção

O deploy de produção **não** usa os scripts deste ambiente. Ele é:

```
push no repo do app → versiona e avisa o repo de deploy
     └─ o repo de deploy builda → GHCR → webhook do Coolify
```

Cada app já vem com o `notify-deploy.yml`, que versiona e avisa. Os Dockerfiles
e o workflow de build ficam no `deploy.<projeto>`, gerados pelo scaffold. O
runbook está em `deploy.<projeto>/docs/coolify-deploy.md`.

---

## Comandos úteis

| | |
|---|---|
| `deploy-infra status` | o que está no ar e em qual contexto Docker |
| `deploy-infra down` | para a base, preservando os dados |
| `deploy-infra reset` | apaga os volumes — banco do zero |
| `./deploy-run dev` | sobe o projeto |
| `./deploy-run dev stop` | para o projeto |
| `deploy-scaffold-app ./code` | confere se os artefatos de build estão em dia |

O ambiente respeita o **contexto Docker ativo**: local por padrão, ou uma VM
remota se você apontar o contexto para lá. Nada muda nos comandos.

---

## Documentação

| | |
|---|---|
| [`docs/technical/starter-kit.md`](docs/technical/starter-kit.md) | **A spec de arquitetura.** Por que o kit é assim: as decisões travadas, a camada de integração com o Spelt, entitlements, provisionamento e o que deliberadamente ficou de fora. |
| [`docs/starter-kit-ideas/`](docs/starter-kit-ideas/) | **Como desenhar o produto antes de codar.** O brief força as decisões que o kit precisa saber — como cobra, o que limita, o que provisiona. |
| [`docs/starter-kit-ideas/TEMPLATE.md`](docs/starter-kit-ideas/TEMPLATE.md) | O molde do brief. É o arquivo que o scaffold copia para `docs/` do seu projeto. |
| [`example-transcribe.md`](docs/starter-kit-ideas/example-transcribe.md) · [`example-menu.md`](docs/starter-kit-ideas/example-menu.md) | Dois briefs preenchidos, para ver o formato funcionando. |
| [`iporto/deploy`](https://github.com/iporto/deploy) | A biblioteca de deploy: `deploy-doctor`, `deploy-infra`, `deploy-run` e o assistente. Tem README próprio. |
| `deploy.<projeto>/docs/coolify-deploy.md` | Gerado no seu projeto pelo scaffold — o runbook de produção. |

---

## Licença

[MIT](LICENSE). Copie, modifique, publique e venda — inclusive comercialmente,
inclusive sem abrir o seu código. O kit existe para virar o seu produto.
