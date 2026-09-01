# Fábrica de Lead — Brief de Produto

> **Status:** brief de referência, preenchido no formato do [`TEMPLATE.md`](TEMPLATE.md). Exemplo de estudo.
> **Não é código nem parte do Spelt.** Doc de staging — destino a definir.
> **Perfil:** provisionamento leve · entitlements ricos (créditos + capacidade) · recorrente.
> **Data:** 2026-08-11.

---

## 0. Cartão de identidade

| Eixo | Este produto |
|---|---|
| Cobrança | recorrente |
| Quota | **ambos** — créditos consumíveis (leads) + limites de capacidade |
| Provisionamento por conta | leve |
| Infra própria do produto | **sim** — motor de match + base da Receita |
| Superfície | cliente final (SSO) |
| Modelo de acesso | pago-direto (recorrente) |

---

## 1. O que é
Plataforma B2B onde empresas ("Contratantes") prospectam **Leads** — outras empresas que precisam do que a Contratante vende. Faz o *match* entre o **ICP** (Perfil de Cliente Ideal) da Contratante e a base de empresas ativas da **Receita Federal**.

## 2. Atores & glossário
| Termo | Significado | Mapeia a |
|---|---|---|
| **Contratante** | Empresa que assina para prospectar | **Account = Spelt Tenant** (conta pagante) |
| **Empresa** | CNPJ cadastrado dentro da conta | entidade do produto (N por conta) |
| **ICP** | Filtros de uma Empresa; gera leads | entidade do produto |
| **Lead** | Match (ICP × CNPJ-alvo) | entidade do produto (consome 1 crédito) |
| **Crédito (Lead)** | Unidade de entrega | **Crédito do Spelt** |

## 3. Fluxo ponta a ponta
1. Contratante entra — **SSO**.
2. Cadastra uma **Empresa** (CNPJ obrigatório).
3. Cria um **ICP** (quantos leads/dia ou ciclo + filtros).
4. Motor roda de X em X min casando **todos os ICPs** contra a Receita.
5. Match → **Lead** → Kanban → (opcional) **webhook** do ICP p/ o CRM.
6. Nutre / gerencia / exporta / descarta o lead.
7. Acabou crédito → Upgrade ou Compra Adicional (one-time no Spelt).

> Toques do kit: **SSO** (passo 1) · **gate** de acesso a cada request · **provisionamento** leve · **webhook de saída** (passo 5).

## 4. Cobrança, créditos & acesso
- **Usa créditos?** Sim.
- **1 crédito =** 1 lead entregue.
- **Recorrente.**
- **Expiram?** Conforme o grant do Spelt (não é regra do produto).
- **Consumo:** 1:1 (1 lead = 1 crédito).
- **Compra avulsa** antes de renovar: **sim** — pagamento único no Spelt → `credit.granted`.
- **Modelo de acesso:** pago-direto (sem free tier / trial declarados).
- **Encaixe:** `seller.credit.granted`/`credit.expired`; o produto gere só o consumo local (§8.6 da spec).

## 5. Entitlements de capacidade
| Chave | Limita | Enforcement |
|---|---|---|
| `empresas` | nº de Empresas por conta | bloqueia criar além |
| `icps_simultaneos` | nº de ICPs rodando ao mesmo tempo | scheduler não roda além |
| `max_leads_geridos` | nº de leads mantidos ao mesmo tempo (ex.: 500) | força limpeza/upgrade |

> Filtros **não** são gated por plano — são da plataforma, dinâmicos e extensíveis.

## 6. Hierarquia de dados
`Account → Empresa (CNPJ/CNAEs) → ICP (filtros) → Lead (ICP × CNPJ-alvo)`. Isolamento por `account_id`.

## 7. Provisionamento & ciclo de vida
- **Ao ativar:** leve — quase nada por conta (a engine/Receita é infra **compartilhada**, fora do ciclo de conta).
- **Up/down:** ajusta capacidade (empresas / ICPs / leads geridos).
- **Teardown (cancel/suspend):** encerra ICPs e a entrega; dados retidos conforme política.

## 8. Downgrade — política
- **(a) Bloquear no Spelt** (via `feature_usage`): se o uso atual (empresas / ICPs / leads geridos) **> limite do plano-alvo**, o portal Customer bloqueia o downgrade. Regra do dono: não permitir descer abaixo do uso.
- `onPlanChanged` reconcilia o que passar.

## 9. Superfície da Platform (telas)
Cadastro de Empresa · builder de ICP (filtros dinâmicos) · **Kanban** (configurável) · **CRM leve** (nutrição) · Relatórios · Export.

## 10. Integrações externas
Nesta versão, só o **webhook de saída por ICP** para o CRM do Contratante (ver §12). Sem conectores OAuth de import.

## 11. API do produto para os usuários?
**Não** (nesta versão).

## 12. Webhook de saída?
**Sim** — **por ICP** → CRM externo do Contratante. Feature do produto, distinta dos webhooks Spelt↔produto.

## 13. Módulos opcionais do kit usados
- **webhook de saída** (§12).
- *(media/file: opcional, se guardar anexos — não essencial.)*

## 14. Infra própria (fora do kit)
**Sim:** motor de match (itens CAPEX/COGS/OPEX ~39k + categorias/`probable_needs` + ligação por CNAE) + base da Receita processada e enriquecida (e-mail/telefone/site). Infra **compartilhada** do produto, fora do ciclo de conta e do kit.

## 15. Mapa de acoplamento
- **Spelt entrega:** identidade + SSO · billing · **Créditos** (grant/expire, incl. compra avulsa) · plano/entitlements · `change-plan` · gate.
- **Produto constrói:** motor de match · infra da Receita · Empresa/ICP/Lead · Kanban · CRM · webhook de saída · export · pacing/consumo de créditos.
- **Kit facilita:** telas · fila · **credit-sync** · webhook de saída.
- **Kit absorve:** credit-sync (núcleo) · webhook de saída (módulo opcional).

## 16. Decisões em aberto
1. Consumo **local** vs. reportado ao Spelt (padrão: local).
2. **Dedup** dentro do mesmo ICP (uma vez por ICP vs. reentrada após N dias).
3. Mudança de plano — **resolvido:** trava via `feature_usage` no Spelt.
4. Período do ciclo de créditos — ancorar no `current_period` do Spelt.
5. Reparte do `leads/dia` — por ICP, com o saldo mensal como orçamento comum da conta.

## 17. Compliance / operação
LGPD na entrega de dados de contato (base legal B2B, opt-out) — responsabilidade do produto.

---

## Mecânica do produto (extras — específico do produto)

- **ICP & scheduling:** cada ICP tem uma taxa (leads/dia ou /ciclo); o motor roda de X em X min sobre **todos** os ICPs da plataforma, respeitando a taxa de cada um e o **saldo de créditos da conta** (orçamento comum).
- **Semântica do Lead:** **compartilhado** entre Contratantes (sem exclusividade/reserva); o mesmo CNPJ em N ICPs consome **N créditos**; `max_leads_geridos` força limpeza/upgrade no tempo.
- **Monetização quando acaba o crédito:** Upgrade (mais créditos/ciclo) ou Compra Adicional avulsa (pagamento único) → `credit.granted`.
- **Motor de match:** do CNPJ → CNAE → o que a Empresa vende → liga a empresas cujo CNAE indica que **precisam comprar** aquilo (via os itens CAPEX/COGS/OPEX e o `probable_needs` das categorias).

---

## Autoavaliação (rubrica)
✅ Cartão · ✅ crédito+capacidade mapeados ao Spelt · ✅ fronteira kit×produto (§15) · ✅ provisionamento+downgrade com política · ✅ módulos opcionais · ✅ infra própria nomeada · ✅ decisões explícitas. **Escopo rico.**
