# MailValidation.io — Brief de Produto

> **Status:** brief de referência, preenchido no formato do [`TEMPLATE.md`](TEMPLATE.md). Exemplo de estudo.
> **Não é código nem parte do Spelt.** Doc de staging — destino a definir.
> **Perfil:** pay-as-you-go consumível · créditos com custo por tier · engine própria.
> **Data:** 2026-08-11.

---

## 0. Cartão de identidade

| Eixo | Este produto |
|---|---|
| Cobrança | **pay-as-you-go** (compra única; recorrente também possível) |
| Quota | créditos consumíveis (com **custo por tier**) |
| Provisionamento por conta | leve |
| Infra própria do produto | **sim** — engine de validação (SMTP/TELNET, pool de IPs) |
| Superfície | cliente final (SSO) |
| Modelo de acesso | pago-direto (compra de créditos) |

---

## 1. O que é
Plataforma de **validação de e-mails**. O usuário faz **upload de listas** de contatos e o sistema valida em **3 tiers** de profundidade. Download/export do resultado.

## 2. Atores & glossário
| Termo | Significado | Mapeia a |
|---|---|---|
| **Usuário** | Quem valida e-mails | **Account = Spelt Tenant** |
| **Lista / Upload** | Lote de contatos submetido | entidade do produto |
| **Resultado** | Validação por e-mail e por tier | entidade do produto |
| **Crédito** | Unidade de validação | **Crédito do Spelt** |

## 3. Fluxo ponta a ponta
1. Usuário **compra créditos** (compra única).
2. Entra — **SSO**.
3. **Upload** da lista (ou **import** via OAuth de Mailchimp/Outlook/RD).
4. Escolhe o **tier** por lote (ou funil).
5. Engine valida **em fila**; debita crédito **por operação** (custo do tier).
6. **Download/export** do resultado (retido 90d).
> Alternativa: **API do produto** valida direto no sistema do usuário.

## 4. Cobrança, créditos & acesso
- **Usa créditos?** Sim.
- **1 crédito =** 1 validação.
- **Pay-as-you-go** (compra única); recorrente também possível.
- **Expiram?** Sim.
- **Consumo:** **custo por operação (tier)** — mecânica=1, existência=N, uso=M (**Modelo B recomendado** — ver Mecânica do produto).
- **Compra avulsa:** é o próprio modelo (packs de crédito).
- **Modelo de acesso:** pago-direto.
- **Encaixe:** `seller.credit.granted`/`credit.expired`; consumo/custo por tier são 100% do produto (§8.6 da spec).

## 5. Entitlements de capacidade
| Chave | Limita | Enforcement |
|---|---|---|
| `validacoes_simultaneas` | jobs de upload em paralelo | produto bloqueia |
| `rate_limit_api` | chamadas/min na API | produto |
| `retencao_dias` | 90 (talvez plan-driven) | expurgo por command |

## 6. Hierarquia de dados
`Account → Lista/Upload → Contatos/E-mails → Resultado (por e-mail, por tier)`. Isolamento por `account_id`.

## 7. Provisionamento & ciclo de vida
- **Ao ativar:** nada pesado por conta (a engine é **compartilhada**).
- **Up/down:** N/A (pay-as-you-go).
- **Teardown (cancel):** expurgo do resultado conforme retenção.

## 8. Downgrade — política
**N/A** no pay-as-you-go (acabou o crédito, compra mais). Se virar recorrente com capacidade, aplicar (a) bloquear / (c) soft-cap.

## 9. Superfície da Platform (telas)
Upload · acompanhamento de jobs · resultados · download/export · conectores (OAuth) · gestão de **API keys**.

## 10. Integrações externas
Mailchimp, Outlook (Contatos), RD Station — **conectores do produto** (OAuth, estilo Socialite), **ler** contatos; **write-back opcional** (marcar inválido na origem — diferencial, escolha do usuário). Nada a ver com o Marketplace do Spelt.

## 11. API do produto para os usuários?
**Sim** — chaves próprias (ex.: `mval_…`), que **moram no produto** (não no Spelt). Consome do **mesmo saldo** de créditos que o upload.

## 12. Webhook de saída?
**Não** (nesta versão). O envio de dados a plataformas se dá pelos conectores (§10).

## 13. Módulos opcionais do kit usados
- **API-keys do produto** (§11).
- **media/file** (armazenamento de listas/resultados).

## 14. Infra própria (fora do kit)
**Sim:** engine de validação — mecânica (regex), existência (DNS/MX), e sobretudo **Uso (TELNET/handshake SMTP em escala)** com pool de IPs, reputação, rate-limit por domínio e retries. Infra **compartilhada** do produto.

## 15. Mapa de acoplamento
- **Spelt entrega:** identidade + SSO · billing · **Créditos** (grant/expire, incl. compra única + expiração) · gate.
- **Produto constrói:** engine (3 tiers) · consumo/custo por tier · upload/listas · conectores OAuth · write-back · download/export · **API própria + chaves**.
- **Kit facilita:** telas · fila · **módulo de API-keys do produto**.
- **Kit absorve:** credit-sync (núcleo) · API-keys (módulo opcional).

## 16. Decisões em aberto
1. **Modelo de crédito:** uniforme + custo-por-tier (**recomendado**) vs. tier-bound vs. priced-only-at-purchase (vaza economia).
2. **Estrutura de planos:** pack de créditos + 3 tiers de consumo vs. 3 planos por tipo (com/sem gate de TELNET).
3. **Capacidade plan-driven?** retenção/concorrência/rate-limit fixos vs. por plano.
4. **Write-back:** quais plataformas suportam marcar inválido, e como.

## 17. Compliance / operação
Validação de Uso (TELNET) pressiona reputação de IP → IPs dedicados + rate-limit por domínio. LGPD: listas de terceiros submetidas pelo usuário (base legal do usuário); o produto trata retenção 90d + expurgo.

---

## Mecânica do produto (extras — específico do produto)

- **Os 3 tiers:** **Mecânica** (sintaxe: `@`, typo) · **Existência** (mecânica + DNS/MX + tudo até "quase enviar") · **Uso** (abre TELNET/handshake SMTP até o "quase envio" p/ ver se aceita). Custo de entrega crescente.
- **Funil vs. direto:** cascata (mecânica → sobreviventes → existência → uso) **ou** tudo num tier escolhido.
- **Análise do modelo de crédito (o vazamento):** "1 crédito uniforme roda qualquer tier a 1:1, diferenciado só no preço de compra" **não fecha** se os planos têm preços diferentes — crédito barato (mecânica) rodando TELNET faz o dono perder na entrega. Se fossem intercambiáveis a 1:1, ninguém compraria o plano caro. **Modelo B (recomendado):** créditos **uniformes** + **custo por tier no consumo** — o Spelt segue burro (só concede), "o que sobra usa no outro" funciona, e a economia fecha.

---

## Autoavaliação (rubrica)
✅ Cartão · ✅ crédito mapeado ao Spelt (custo por tier no produto) · ✅ fronteira kit×produto (§15) · ✅ provisionamento+downgrade (N/A justificado) · ✅ módulos opcionais · ✅ infra própria nomeada · ✅ decisões explícitas. **Escopo rico.**
