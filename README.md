# Starter Kit

Um ponto de partida para subir um SaaS: **API + Platform** em Laravel, com
autenticação, assinatura, créditos e webhooks já resolvidos — e um ambiente de
desenvolvimento que sobe com dois comandos.

A cobrança e o controle de acesso são delegados ao **[Spelt](https://spelt.com.br)**:
o kit já traz o SSO, o recebimento de webhooks e o modelo de créditos e planos.
Para colocar um produto no ar de verdade você precisa de uma conta lá — mas
**não para desenvolver**, como o passo 7 mostra.

Não é um framework nem uma biblioteca. É um **repositório para copiar**: você
gera o seu produto a partir dele e o código passa a ser seu.

---

## O que vem pronto

| | |
|---|---|
| `code/api` | API Laravel — contas, assinaturas, créditos, webhooks, tokens |
| `code/platform` | Painel do usuário — Laravel + Vite |
| `code/www` | Site institucional (opcional) |
| Artefatos de build | `Dockerfile`, `.deploy/` e workflow do GitHub Actions em cada app |
| Ambiente de dev | gerado pela biblioteca de deploy, com nginx, PHP-FPM, banco e mail |

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

---

## Subir em dez minutos

> Os comandos usam **`acme.com`** como exemplo. Troque pelo seu domínio em
> **todos** — ele determina o nome dos containers (`acme-php-api`), o diretório
> dos apps (`code/api.acme.com`) e as linhas do `/etc/hosts`. Trocar pela metade
> gera erros que não apontam para a causa.
>
> E defina onde o kit está, porque o passo 5 precisa do caminho:
>
> ```bash
> KIT=~/starter-kit    # o diretório onde você clonou ESTE repositório
> ```

### 1. A biblioteca de deploy

```bash
git clone https://github.com/iporto/deploy.git ~/.deploy-scripts
export PATH="$HOME/.deploy-scripts:$PATH"      # ponha no seu .bashrc/.zshrc
```

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

Na primeira execução ele cria o `infra/.env.dev` e pede duas senhas. Gere com
`openssl rand -hex 24` — o próprio arquivo explica. Rode de novo e sobem Traefik,
MariaDB, Redis e Mailpit, na rede `shared`.

### 4. O banco do seu produto

```bash
deploy-infra createdb acme --print-env > /tmp/acme-db.env
```

Cria database, usuário e senha. Você não abre cliente SQL nenhum.

### 5. Gerar o produto

Se ainda não clonou este repositório:

```bash
git clone https://github.com/Codijo/starter-kit.spelt.com.br.git "$KIT"
```

```bash
deploy-project-scaffold acme.com \
  --from-kit "$KIT" \
  --db-env /tmp/acme-db.env \
  --apply
```

Isso cria `deploy.acme.com/` com os apps já renomeados (`code/api.acme.com`,
`code/platform.acme.com`), os envs apontando para a infra, e o ambiente de dev.

### 6. Resolver os domínios

O comando imprime as linhas. Acrescente ao `/etc/hosts` **da máquina onde roda o
navegador**:

```
127.0.0.1 acme.test www.acme.test
127.0.0.1 api.acme.test
127.0.0.1 platform.acme.test
```

> ⚠️ Sem isso você **não toma erro** — `.test` não resolve e o navegador tenta
> uma busca ou um host aleatório. O sintoma não aponta para a causa.

### 7. Conferir antes de subir

```bash
deploy-doctor ~/deploy.acme.com
```

Agora com o projeto, ele verifica o que o passo anterior pediu: se os domínios
resolvem e se o `DB_HOST` dos apps aponta para a infra. É a checagem que pega o
erro mais confuso do fluxo — sem a linha no `hosts` **o navegador não dá erro**.

### 8. Subir

```bash
cd deploy.acme.com
./deploy-run dev
```

> ⏳ **A primeira subida leva alguns minutos.** Cada app roda `composer install`
> e o Platform ainda faz `npm install`. Enquanto isso, `/login` já responde mas o
> painel devolve **500 com "Vite manifest not found"** — é o servidor do Vite
> ainda subindo. Espere o arquivo `code/platform.acme.com/public/hot` aparecer.

### 9. Migrar e entrar

```bash
docker exec acme-php-api sh -c 'cd api.acme.com && php artisan migrate --force'
docker exec acme-php-api sh -c 'cd api.acme.com && php artisan dev:token'
```

> O nome do container é `<slug>-php-api` e o app fica em `api.<seu-dominio>` dentro
> dele. O `deploy-project-scaffold` imprime estes dois comandos já preenchidos ao
> terminar — copie de lá.

O `dev:token` cria conta, assinatura e usuário, e imprime um link pronto:

```
http://platform.acme.test/auth/dev-login?token=…
```

Abra e você está no painel. **Sem conta no Spelt** — é o caminho de
desenvolvimento, e ele só existe fora de produção.

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
push no repo do app → GitHub Actions builda → GHCR → webhook do Coolify
```

Cada app já vem com `Dockerfile`, `.dockerignore`, `.deploy/` e `build.yml`. O
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
| `deploy-app-scaffold ./code` | confere se os artefatos de build estão em dia |

O ambiente respeita o **contexto Docker ativo**: local por padrão, ou uma VM
remota se você apontar o contexto para lá. Nada muda nos comandos.
