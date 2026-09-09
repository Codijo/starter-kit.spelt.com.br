# Template de Brief — Produto do SaaS Kit

> **Para que serve:** preencher **antes** de escrever código de um novo produto sobre o [Starter Kit](../technical/starter-kit.md). Filtra limitações no papel e produz um escopo rico.
> **Como usar:** copie este arquivo para `docs/starter-kit-ideas/<produto>.md` e responda cada `▢`. Cada seção diz *o que responder* e *por que importa* (a costura do kit que ela toca). Os exemplos vêm de dois briefs fictícios preenchidos — **TR** = [Transcreve](exemplo-transcreve.md) (créditos, API, webhook) e **CD** = [Cardápio](exemplo-cardapio.md) (capacity-only, página pública).
> Enquanto houver `▢` sem resposta não-vaga, o escopo **não** está fechado (ver a Rubrica no fim).

---

## 0. Cartão de identidade (classificação rápida)

Uma linha cada — é o que posiciona o produto no mapa:

| Eixo | Opções | Este produto |
|---|---|---|
| Cobrança | recorrente · pay-as-you-go · misto | ▢ |
| Quota | créditos consumíveis · limites de capacidade · ambos · nenhuma | ▢ |
| Provisionamento por conta | nenhum · leve · pesado (long-running) | ▢ |
| Infra própria do produto | sim (qual?) · não (só CRUD) | ▢ |
| Superfície | cliente final (SSO) · outra | ▢ |
| Modelo de acesso | pago-direto · free tier/freemium · trial · misto | ▢ |

> ex. **TR:** misto · créditos · leve · sim (fila de transcrição + storage) · cliente final.
> ex. **CD:** recorrente · capacidade · leve · sim (renderizador público + domínio) · cliente final + página pública.

---

## 1. O que é  *(1–3 frases)*
**Guia:** o valor que o cliente final recebe. Nada de gestão — billing/suporte/planos são do Spelt.
▢ _Preencher:_

## 2. Atores & glossário
**Guia:** quem usa e como cada termo mapeia ao kit. Regra fixa: **Account = Spelt Tenant**.
▢ _Preencher (termo → significado → mapeia a):_
> ex. TR: Contratante = Account; Mídia/Transcrição = entidades do produto.

## 3. Fluxo ponta a ponta
**Guia:** da compra ao uso. Marque onde entram SSO, webhook, gate e provisionamento.
▢ _Preencher (passos 1…n):_

## 4. Cobrança, créditos & acesso  *(a costura mais crítica)*
**Guia:** quota consumível **não é primitivo novo** — é o módulo de **Créditos** do Spelt (`credit.granted`/`credit.expired`); o produto só gere o consumo local (§8.6 da spec). **Se o produto é só capacidade (sem créditos), diga isso e pule os itens de crédito.**
- **Usa créditos consumíveis?** (não → capacity-only, siga para §5) ▢
- O que é **"1 crédito"** neste produto? ▢
- Recorrente, pay-as-you-go ou misto? ▢
- Créditos **expiram**? ▢
- Consumo **1:1** ou **custo por operação**? (se houver tiers/pesos, descreva a tabela) ▢
- **Compra avulsa** antes de renovar? ▢
- **Modelo de acesso:** free tier/freemium? trial (o que entrega e por quantos dias)? — o Spelt já suporta plano R$0 e trial, sem primitivo novo. ▢
> ex. TR: 1 crédito = 1 minuto; misto; custo por operação (1× / 1,5× / 2×); compra avulsa sim.
> ex. CD: **não usa créditos** — é capacity-only, e diz isso aqui antes de pular para a §5.

## 5. Entitlements de capacidade  *(tetos estáticos)*
**Guia:** o que **não** é crédito. Lidos das features do plano no Spelt (`GET /plan/{id}`, §9). Liste as chaves:
▢ _Preencher (chave → o que limita → enforcement):_
> ex. TR: `duracao_maxima_minutos`, `transcricoes_simultaneas`, `retencao_dias`. CD: `unidades`, `itens_por_cardapio`, `dominio_proprio`.

## 6. Hierarquia de dados
**Guia:** sub-entidades do produto dentro da conta (não é tenancy do Spelt). Isolamento por `account_id`.
▢ _Preencher (Account → … → …):_
> ex. TR: Account → Pasta → Mídia → Transcrição. CD: Account → Unidade → Cardápio → Item.

## 7. Provisionamento & ciclo de vida
**Guia:** o kit dá os ganchos (`provision`/`onPlanChanged`/`teardown`) por fila; o produto implementa (§10).
- Provisiona **o quê** ao ativar? (nada / leve / pesado) ▢
- Reprovisiona em up/down? ▢
- Teardown ao cancelar/suspender? ▢

## 8. Downgrade — política
**Guia:** 3 caminhos (escolha um **por feature**): **(a) bloquear no Spelt** via reporte de utilização (`PUT feature_usage`) — impede antes; **(b) reconciliar** no `onPlanChanged` (desprovisiona excedente); **(c) soft-cap** (esconde/bloqueia novos).
▢ bloquear no Spelt · ▢ reconciliar · ▢ soft-cap — **e por qual feature cada um:**
> ex. CD: (b) reconciliar em `unidades` · (c) soft-cap em `itens_por_cardapio`.
> ex. CD: `dominio_proprio` → (b) volta ao subdomínio e remove o certificado;
> `unidades` acima do limite → (b) despublica o excedente, sem apagar nada.

## 9. Superfície da Platform (telas)
**Guia:** deriva o shell do portal Customer. Liste as telas do produto.
▢ _Preencher:_
> ex. TR: biblioteca de mídias, editor de transcrição, upload, chaves de API, consumo.

## 10. Integrações externas
**Guia:** conectores externos são **do produto** (estilo Socialite), não o Marketplace do Spelt.
▢ _Preencher (quais · ler e/ou escrever):_
> ex. TR: Google Drive e Dropbox (ler mídia) · Notion (escrever o texto).

## 11. API do produto para os usuários?
**Guia:** se sim, o produto emite **chaves próprias** (módulo opcional §22, herdável do `dev_apis`). Consome do mesmo saldo de créditos?
▢ _Preencher (sim/não · prefixo · consome crédito?):_
> ex. TR: sim · `tsc_…` · mesmo saldo. CD: não.

## 12. Webhook de saída?
**Guia:** emitir eventos para o sistema/CRM do cliente (módulo opcional §22).
▢ _Preencher (por qual grão: conta / entidade):_
> ex. TR: sim, por conta (`transcricao.concluida`). CD: não.

## 13. Módulos opcionais do kit usados
**Guia:** nenhum é padrão (kit mínimo). Marque o que este produto liga:
▢ API-keys do produto · ▢ webhook de saída · ▢ usage-based metering · ▢ media/file · ▢ provisionamento de domínio/SSL (Cloudflare for SaaS) · ▢ notificação operacional própria · ▢ magic-link de retorno.
> **Atenção:** *usage-based metering* = reportar **consumo ao Spelt para cobrar** (overage). **Não** é o mesmo que métricas do produto (visitas, cliques, relatórios) — isso é dado/infra do produto (§14), não módulo do kit.

## 14. Infra própria (fora do kit)
**Guia:** o que o produto **opera fora do CRUD** — engine, **renderizador público**, **edge/domínio (Cloudflare)**, **storage de assets**, **analytics**. Infra **leve ainda é infra**: "não robusta" ≠ "nenhuma". Só marque "não" se for realmente **só CRUD** sobre o banco.
▢ _Preencher:_
> ex. TR: fila de transcrição com GPU + storage de áudio. CD: renderizador da
> página pública + domínio/SSL via Cloudflare for SaaS + CDN de imagens.

## 15. Mapa de acoplamento *(fechamento)*
**Guia:** preencher as 4 linhas força a clareza da fronteira.
- ▢ **Spelt entrega:**
- ▢ **Produto constrói:**
- ▢ **Kit facilita:**
- ▢ **Kit absorve** (se surgir algo novo):

## 16. Decisões em aberto
▢ _Liste o que ainda não está decidido — não deixe implícito._

## 17. Compliance / operação
▢ _LGPD, reputação de IP, limites legais — o que o produto trata._

---

## Mecânica do produto (extras — específico do produto)
>
> 

## Rubrica — o brief está "rico"?

O escopo está fechado quando **todos** têm resposta não-vaga:

1. **Cartão de identidade** (§0) preenchido — posiciona no mapa.
2. **Cobrança/quota** mapeadas a **Créditos/capacidade do Spelt** (§4/§5) **sem inventar primitivo novo**.
3. **Fronteira kit × produto** explícita (§15) — dá para dizer o que **não** é do kit.
4. **Provisionamento e downgrade** com política escolhida (§7/§8), não "a definir".
5. **Módulos opcionais** identificados (§13).
6. **Infra própria** isolada e nomeada (§14).
7. **Decisões em aberto** explicitadas (§16) — o que falta é *conhecido*.

Se algum item ficar vago, o revisor aponta exatamente qual — e o escopo ainda não fechou.
