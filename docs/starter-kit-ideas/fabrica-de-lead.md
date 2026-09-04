# Fábrica de Lead — Brief de Produto

> **Status:** ⚠️ **migrado.** A cópia canônica vive em `api.fabricadelead.com.br/docs/product/fabrica-de-lead.md`
> (repo `fabricadelead.com.br`). Este arquivo fica como histórico do exercício de acoplamento — **não editar aqui**.
> Formato do [`TEMPLATE.md`](TEMPLATE.md); construído sobre o [Starter Kit](../technical/starter-kit.md).
> **Perfil:** provisionamento leve · créditos + capacidade · misto (recorrente + avulso) · trial · engine e base de dados próprias · cliente final.
> **Data:** 2026-09-03 · **Revisão:** 2026-09-03 (escopo fechado em sessão de decisão).
> **Antecedente:** existe uma v1 descontinuada, usada como **aprendizado** (não como código) — ver a seção *Aprendizados da v1* no fim.

---

## 0. Cartão de identidade

| Eixo | Este produto |
|---|---|
| Cobrança | **misto** (recorrente + compra avulsa) |
| Quota | **ambos** — créditos consumíveis (leads) + limites de capacidade |
| Provisionamento por conta | leve |
| Infra própria do produto | **sim** — motor de match + base da Receita Federal (MongoDB) + pipeline de enriquecimento + lead scoring |
| Superfície | cliente final (SSO) |
| Modelo de acesso | **trial de 7 dias / 35 leads** · pago-direto (recorrente). Sem free tier permanente |

---

## 1. O que é

Plataforma B2B onde empresas ("Contratantes") recebem **Leads** — outras empresas que provavelmente precisam do que elas vendem. O produto faz o *match* entre o **ICP** (Perfil de Cliente Ideal) do Contratante e a base de empresas ativas da **Receita Federal**, enriquecida com dados públicos coletados pela própria Fábrica de Lead.

A promessa não é "busca": é **entrega**. O Contratante configura o ICP uma vez e o motor entrega lead todo dia, sem ação dele — como uma campanha de Ads, que se calibra ajustando taxa, filtros e corte de qualidade.

## 2. Atores & glossário

| Termo | Significado | Mapeia a |
|---|---|---|
| **Contratante** | Empresa que assina para prospectar | **Account = Spelt Tenant** (conta pagante) |
| **Usuário** | Pessoa do time comercial que opera a plataforma. A conta nasce no Spelt e chega por SSO; o **Papel** é atribuído aqui | usuário vinculado ao **Account** |
| **Papel** | `Admin` · `Gestor` · `Operador` — define o que o Usuário pode fazer (§6.1) | atributo do Usuário |
| **Empresa** | CNPJ do próprio Contratante, cadastrado dentro da conta | entidade do produto (N por conta) |
| **Área** | Setor/time da Empresa que prospecta. Organiza ICPs, Kanban e webhook | entidade do produto (N por Empresa) |
| **Produto** | O que a Empresa vende. Carrega o **perfil de consumo** que alimenta o match | entidade do produto (N por Empresa) |
| **ICP** | Perfil de Cliente Ideal: regras de filtro + taxa diária + score mínimo. Pertence a uma **Área** e aponta para **um Produto** | entidade do produto (N por Área) |
| **Lead** | Match entregue (**ICP** × **Perfil Enriquecido**) | entidade do produto (**consome 1 crédito na entrega**) |
| **Contato** | Pessoa de contato/decisor, **copiada** do Perfil Enriquecido para a conta no momento da entrega | entidade do produto (N por Lead) |
| **Negócio** | Oportunidade comercial aberta quando um Lead é **Aceito** | entidade do produto (1 por Lead aceito) |
| **Pipeline** | Conjunto de **Etapas** do funil da Área. Sistema entrega um default editável | entidade do produto (1 por Área) |
| **Kanban** | A visualização dos Negócios sobre o Pipeline da Área | tela, não entidade |
| **Empresa CNPJ** | Base bruta alimentada pelos arquivos da Receita Federal | entidade **do sistema** (não pertence a Contratante) |
| **Perfil Enriquecido** | `Empresa CNPJ` + dados coletados (contato, site, redes, decisores, perfil de consumo, score) | entidade **do sistema** |
| **Lista de Supressão** | Registro global de titulares que exerceram oposição/eliminação | entidade **do sistema** (§17) |
| **Crédito (Lead)** | Unidade de entrega | **Crédito do Spelt** |

## 3. Fluxo ponta a ponta

1. Contratante escolhe um plano e conclui a compra no Spelt (ou entra no **trial**).
2. Pagamento/trial aprovado → webhook → **provisionamento leve** do Account, plano, entitlements e **créditos**.
3. Primeiro Usuário entra na plataforma por **SSO** e nasce **Admin** (§6.1).
4. **Wizard de onboarding**, em uma tela: informa o CNPJ da **Empresa** e o sistema puxa razão social, CNAEs e porte da própria base `Empresa CNPJ`; cria a **Área** e o **Pipeline default**; sugere um **Produto** com `probable_needs` derivado do CNAE; e propõe um **ICP de exemplo** pronto para ativar ou ajustar.
5. *(alternativa)* Cadastra Empresa, Área e Produto pelos CRUDs, na ordem natural.
6. Confirma ou ajusta o **ICP**: regras de filtro, **taxa diária** e **score mínimo** (default 60).
7. O **gate** valida assinatura ativa e Papel; o ICP ativo entra na varredura do motor.
8. O motor varre os ICPs ativos **a cada 15 minutos** e casa cada um contra os **Perfis Enriquecidos**, respeitando taxa diária, score mínimo, janela de dedup e **saldo de créditos da conta**.
9. Match → **debita 1 crédito** → cria o **Lead** (`Novo`) e **copia os Contatos** para a conta.
10. **Webhook de saída** da Área dispara `lead.delivered` para o CRM do Contratante (opcional).
11. O Operador escolhe a **Área**, entra e tria a fila: `Aceito`, `Descartado` (com motivo) ou `Arquivado`.
12. `Aceito` → abre um **Negócio** no Kanban da Área.
13. Nutre, move de etapa, exporta, ganha ou perde.
14. Saldo zerado → **os ICPs param de rodar** e o Contratante é **notificado** (§13.1). Upgrade ou Compra Adicional no Spelt → `credit.granted` → os ICPs retomam.

> **Toques do kit:** **provisionamento** leve (passo 2) · **SSO** (passo 3) · **gate** de acesso a cada request (passo 7) · **credit-sync** (passos 2, 9 e 14) · **webhook de saída** (passo 10) · **notificação operacional própria** (passo 14, §13.1).

## 4. Cobrança, créditos & acesso

- **Usa créditos consumíveis?** Sim.
- **1 crédito =** 1 Lead entregue.
- **Recorrente, pay-as-you-go ou misto?** **Misto** — o plano concede créditos recorrentes, com opção de compra avulsa a qualquer momento.
- **Créditos expiram?** Conforme o grant do Spelt; não é regra do produto.
- **Consumo:** 1:1 — 1 Lead entregue = 1 crédito.
- **Momento do débito: na entrega.** O motor debita ao criar o Lead. **O crédito é consumido sem ação do Contratante — isso é o serviço, não um efeito colateral.** O produto entrega lead sem que ele faça nada; ele confia no ICP e no match. A contrapartida obrigatória é transparência: saldo, taxa de entrega e histórico de consumo sempre visíveis (§9).
- **Estorno:** **não existe.** O produto não deve gerar lead falho — o score mínimo e a validação de contato filtram antes de gastar. O Contratante marca o Lead como `Descartado` **com motivo**, e esse motivo é sinal de calibração do ICP, não pedido de devolução. Se o volume de descarte ficar alto, o tratamento é revisão da engine (§16), não crédito de volta.
- **Dedup:** um mesmo CNPJ **não** é re-entregue para a mesma conta dentro da **janela de dedup de 90 dias**. Passada a janela, a re-entrega é permitida e cobra novo crédito: a empresa mudou de contexto e a reabordagem é legítima.
- **Saldo zero:** os ICPs da conta **param de rodar** (`paused_no_credit`) — nenhum ciclo de match, nenhum enriquecimento, nenhum consumo de hardware. A plataforma segue acessível para triar, trabalhar Negócios e exportar o que já foi entregue.
- **Compra avulsa antes de renovar?** Sim — pagamento único no Spelt → `credit.granted` → ICPs retomam automaticamente.
- **Taxa padrão de entrega — regra, não número fixo:**

  ```
  taxa padrão do ICP = ⌈ (créditos do ciclo × 40%) ÷ dias do ciclo ⌉
  ```

  Exemplos: 1.000 créditos/mês → **14/dia** · 5.000/mês → **67/dia**. A regra escala com o plano, coisa que um número fixo não faria (15/dia num plano de 5.000 entregaria 9% do pacote).

  Os **40%** são deliberados: o padrão entrega menos da metade do pacote para que o Contratante calibre antes de acelerar, não afogue o SDR e não queime o mês na primeira semana — e o produto não opera no custo máximo em toda conta. Quem quiser acelerar sobe a taxa (só `Gestor` ou `Admin`, §6.1).
- **Modelo de acesso:** trial de **7 dias / 35 leads** de cortesia, entregues a **5/dia**, via `trial_value`/`trial_mode` do plano no Spelt. Sem free tier permanente. O teto do trial é o **número de créditos**, não o tempo — assim o COGS de aquisição fica limitado e previsível.
- **O trial é a exceção à regra dos 40%.** Ali a taxa é `créditos ÷ dias` (35 ÷ 7 = 5/dia), porque o objetivo se inverte: no plano pago, crédito não consumido protege margem; no trial, crédito não consumido é **marketing desperdiçado** — o COGS de aquisição já foi pago e o cliente não viu o produto funcionando. O trial precisa durar os 7 dias inteiros para o Contratante viver o ciclo completo: recebe, avalia, ajusta o ICP e vê a entrega melhorar no dia seguinte.
- **Gate:** antes de cada ciclo do motor, verifica assinatura ativa (`has_access`) e saldo local > 0. Consultar, triar, exportar e trabalhar Negócios **não** consome crédito.
- **Encaixe:** `seller.credit.granted` / `seller.credit.expired`; o produto gere só o consumo e o saldo local (§8.6 da spec).

## 5. Entitlements de capacidade

Lidos das features do plano no Spelt via `SpeltClient::getPlan($planId)` (`GET /plan/{id}`, §9 da spec) e gravados como snapshot em **`billing_subscriptions.entitlements`** (JSON). Leitura O(1) no request via `$account->entitlement(...)`.

| Chave | O que limita | Enforcement |
|---|---|---|
| `plan-feature-company` | Empresas (CNPJs próprios) cadastradas na conta | Ao criar ou reativar uma Empresa |
| `plan-feature-area` | Áreas por Empresa | Ao criar ou reativar uma Área |
| `plan-feature-product` | Produtos por Empresa | Ao criar ou duplicar um Produto |
| `plan-feature-icp` | ICPs **ativos** na conta | Ao criar ou ativar um ICP |
| `plan-feature-user` | Usuários vinculados à conta | Ao convidar ou reativar um usuário |
| `plan-feature-managed-lead` | Leads **em aberto** (`Novo` + `Aceito`) na conta | Antes de entregar um novo Lead |

**Notas:**

- `Descartado` e `Arquivado` **não** contam para `plan-feature-managed-lead`. É a triagem que libera vaga — o mesmo ato que calibra o ICP.
- **Não existe teto de Negócio.** O teto de Leads em aberto já regula a entrada, e Negócio é consequência direta do Lead; limitar duas vezes puniria exatamente o cliente que está convertendo bem.
- **Não existe teto de vazão diária por plano.** A taxa diária é definida pelo Contratante em cada ICP; o limite superior é o **saldo de créditos** (modelo Ads: o orçamento é o crédito). O default sai da **regra dos 40%** (§4), deliberadamente abaixo do que o pacote permite, para dar espaço de calibração antes de acelerar.
- **Export não é limitado por plano.** O dado já foi pago em crédito.
- Créditos de Lead não são entitlement de capacidade: são o módulo de **Créditos do Spelt** (§4).

## 6. Hierarquia de dados

**Hierarquia principal:**

```
Account (Contratante)
└── Empresa (CNPJ próprio)
    ├── Produto  (perfil de consumo de quem compra)
    └── Área  (time)
        ├── Pipeline → Etapa
        └── ICP  (→ 1 Produto, obrigatório)
            └── Lead  (ICP × Perfil Enriquecido)
                ├── Contato  (copiado na entrega)
                └── Negócio  (quando o Lead é Aceito) → Etapa do Pipeline da Área
```

**Entidades relacionadas:**

- Account → Usuário. O Usuário **não** é preso a uma Área: ele vê a listagem de Áreas e escolhe em qual entrar. Não há gate por Área — é navegação, não permissão. O gate existe por **Papel** (§6.1).
- ICP → **1 Produto** (obrigatório). O perfil de consumo do Produto entra no match como regras `OR` sobre os campos `CONSUMES_*`.
- Webhook de saída → configurado **por Área**.

### 6.1 Papéis

O Usuário chega por SSO (a conta é criada no portal Customer do Spelt); o **Papel** é atribuído dentro do produto, porque é conceito do produto — o Spelt não conhece "quem pode mexer no ICP".

| Papel | Pode |
|---|---|
| **Operador** (SDR) | Entrar em qualquer Área, triar Leads, editar Contatos, trabalhar Negócios, mover etapas, exportar. |
| **Gestor** | Tudo do Operador **+** criar e editar ICP, **alterar taxa diária e score mínimo**, gerir Áreas, Produtos, Pipelines e webhook de saída. |
| **Admin** | Tudo do Gestor **+** gerir Empresas, atribuir Papéis e acessar o SSO reverso para a assinatura no Spelt. |

**Por que existe:** a taxa diária do ICP **gasta dinheiro**. Subir de 15 para 300/dia queima um pacote de 1.000 em três dias, com débito automático e sem estorno (§4). Separar quem *consome* o lead de quem *decide o ritmo do gasto* é a única proteção do saldo — e a fronteira Gestor/Operador é exatamente essa linha.

O primeiro usuário da conta nasce **Admin**. `plan-feature-user` conta todos os Papéis igualmente.

**Entidades do sistema (fora da conta, compartilhadas):**

```
Empresa CNPJ (Receita Federal)  →  Perfil Enriquecido  →  Score
Lista de Supressão (global, LGPD)
```

Todas as entidades do produto são isoladas por `account_id`. Nenhuma query cruza contas.

## 7. Provisionamento & ciclo de vida

- **Ao ativar:** leve — cria o Account local, sincroniza plano, entitlements e status de acesso. Nada de infra dedicada: motor, base da Receita e pipeline de enriquecimento são **compartilhados** entre todos os Accounts, fora do ciclo de conta.
- **Recursos dedicados:** nenhum. O isolamento é lógico, por `account_id`.
- **Up/down:** `onPlanChanged` re-hidrata os entitlements e aplica os novos tetos imediatamente. Upgrade de crédito destrava ICPs pausados por saldo.
- **Suspensão** (`past_due` / `access_blocked`): **retira o acesso à plataforma e para os ICPs.** Nenhum ciclo de match roda, nenhum crédito é consumido, nenhum enriquecimento é disparado. Os dados permanecem intactos; restaurado o acesso, os ICPs voltam a rodar do ponto em que pararam.
- **Cancelamento:** ICPs param imediatamente. A conta entra em **somente leitura por 30 dias** — o Contratante ainda entra, consulta e **exporta** Leads, Contatos e Negócios.
- **Teardown:** encerrados os 30 dias, a conta fecha e os dados do Contratante são **eliminados em até 90 dias** contados do cancelamento. Os dados do sistema (Empresa CNPJ, Perfil Enriquecido) não são afetados — não pertencem à conta.
- Os handlers `provision`, `onPlanChanged` e `teardown` rodam por fila e são idempotentes (§10 da spec).

## 8. Downgrade — política

| Feature | Política | Comportamento |
|---|---|---|
| `plan-feature-company` | **(a) Bloquear no Spelt** | Impede o downgrade enquanto houver mais Empresas ativas que o limite do plano-alvo. |
| `plan-feature-area` | **(a) Bloquear no Spelt** | Impede o downgrade com Áreas acima do limite — Área carrega ICP, Pipeline e webhook; desprovisionar em silêncio quebraria a entrega. |
| `plan-feature-product` | **(a) Bloquear no Spelt** | Impede o downgrade com Produtos acima do limite. Como todo ICP depende de um Produto, remover Produto sem tratar o ICP deixaria ICP órfão. |
| `plan-feature-icp` | **(a) Bloquear no Spelt** | Impede o downgrade com ICPs ativos acima do limite. Para descer, o cliente desativa ICPs primeiro — e ele decide quais, não o sistema. |
| `plan-feature-user` | **(a) Bloquear no Spelt** | Impede o downgrade enquanto houver mais usuários ativos que o permitido. |
| `plan-feature-managed-lead` | **(c) Soft-cap** | Não bloqueia o downgrade e não apaga nada. Acima do limite, o motor **para de entregar** até a triagem devolver vaga. É a única feature que o cliente não controla no instante (o Lead chega sozinho), então travar o downgrade por ela seria travar por algo que ele não causou. |

- A utilização das cinco features estruturais é reportada ao Spelt via `PUT /seller/external/v1/subscription/{id}/feature-usage` (body `{"feature_usage": {"<slug>": <int>}}`, replace completo), atualizada a cada criação/remoção e re-sincronizada pelo `spelt:reconcile`.
- O portal Customer bloqueia o downgrade **antes** de acontecer; `onPlanChanged` reconcilia o que passar.
- Nenhuma Empresa, Área, Produto, ICP, Lead, Contato ou Negócio é apagado automaticamente em downgrade.

## 9. Superfície da Platform (telas)

| Tela | Função |
|---|---|
| **Início / Dashboard** | Saldo de créditos, entrega do dia e do ciclo, ICPs ativos e pausados, leads novos aguardando triagem. É onde o débito automático fica transparente. |
| **Onboarding (wizard)** | Tela única de partida: CNPJ → autopreenchimento da Empresa pela base do sistema, Área e Pipeline default, Produto sugerido pelo CNAE e **ICP de exemplo** pronto para ativar ou ajustar. Alvo: primeiro ICP ativo em minutos. |
| **Usuários e Papéis** | Lista os usuários da conta (que chegam por SSO) e atribui `Admin` / `Gestor` / `Operador`. Visível só para Admin. |
| **Seleção de Área** | Listagem de todas as Áreas da conta; o usuário escolhe em qual entrar. Sem restrição de acesso — é navegação. |
| **Empresas** | CRUD do CNPJ próprio do Contratante, conferido contra a base `Empresa CNPJ`. |
| **Áreas** | CRUD das Áreas de uma Empresa. |
| **Produtos** | CRUD do que a Empresa vende, incluindo o **perfil de consumo** (o que quem compra isso costuma precisar). |
| **ICPs** | Lista de ICPs da Área, com status, taxa diária, entrega acumulada e saldo projetado. |
| **Builder de ICP** | Montagem das regras (campo · operador · valor · `and`/`or`), escolha do Produto, taxa diária (default pela regra dos 40%, §4) e **score mínimo** (default 60). Mostra estimativa de alcance e avisa quando a primeira entrega depende de enriquecimento ainda na fila. |
| **Fila de Leads** | Triagem: `Novo` → `Aceito` / `Descartado` (com motivo) / `Arquivado`. Filtros por ICP, score, categoria, UF, data. |
| **Detalhe do Lead** | O produto em si: perfil enriquecido da empresa-alvo, contatos e decisores, presença digital, **score explicado** por componente, e as ações de triagem. |
| **Contatos** | Contatos copiados para a conta, com edição e anotação, sem afetar a base do sistema. |
| **Kanban / Negócios** | Negócios da Área sobre o Pipeline, com arrastar entre etapas, valor e prioridade. |
| **Detalhe do Negócio** | Etapa, valor, contato, notas e histórico de movimentação. |
| **Configuração de Pipeline** | Edição das etapas da Área. Nasce com o default do sistema — a maioria dos clientes permanece nele. |
| **Export** | CSV/XLSX de Leads, Contatos e Negócios, com histórico de exportações (registro de auditoria — §17). |
| **Webhook de saída** | Configuração do endpoint da Área, segredo de assinatura e log de entregas/retentativas. |
| **`billing-required`** | Bloqueio padrão do kit, com botão de SSO reverso para o portal Customer do Spelt. |

Gestão de assinatura, plano, faturas, créditos comprados e dados cadastrais usa as telas do **portal Customer do Spelt**, não telas próprias.

**Fora do MVP:** conectores nativos de CRM, enriquecimento de CSV do cliente, API pública, templates prontos de ICP, relatórios avançados de conversão.

## 10. Integrações externas

**MVP: nenhum conector nativo.** A saída de dados acontece por **webhook por Área** (§12) e por **export CSV/XLSX** (§9). Isso cobre a maioria dos casos sem manter OAuth, mapeamento de campos e quebras de API de N plataformas desde o dia 1.

As fontes de dados usadas no enriquecimento (Receita Federal, sites das empresas-alvo, redes sociais públicas) são **infraestrutura do produto** (§14), não conectores configuráveis pelo cliente.

### 10.1 Dependência interna — MailValidation

A validação de e-mail do pipeline de enriquecimento **não é construída aqui**: a Fábrica de Lead consome o **MailValidation** ([`mail-validation.md`](mail-validation.md)), produto irmão sobre o mesmo kit. Decisão tomada para não duplicar pool de IPs, reputação de envio e engine SMTP/TELNET dentro do grupo.

| Item | Definição |
|---|---|
| **Como consome** | API do MailValidation, com chave própria (`mval_…`) — o módulo de API-keys já previsto lá (§11 do brief dele). |
| **Tier usado** | *Existência* (DNS/MX) como padrão do enriquecimento; *Uso* (TELNET) reservado para o contato do Lead que vai ser entregue. Confirmar em §16. |
| **Quem paga** | Conta interna da Fábrica de Lead no MailValidation. **Não** aparece para o Contratante e **não** consome crédito de Lead dele — é COGS do produto (§16 item 7). |
| **Falha do serviço** | O `EmailValidator` é uma parte isolada do pipeline: indisponibilidade não trava as demais. O contato entra sem selo de validação e é reprocessado depois. |
| **Fronteira** | Dependência **server-to-server entre produtos**, invisível para o cliente final. Não é conector do Marketplace nem integração do Contratante. |

### Estágio 2 — conectores de CRM

| Integração | Acesso | Uso |
|---|---|---|
| **RD Station CRM** | Escrita | Criar lead/oportunidade direto no CRM do Contratante. |
| **HubSpot** | Escrita | Criar contato e deal. |
| **Pipedrive** | Escrita | Criar pessoa, organização e deal. |

Conectores são implementados e mantidos pelo produto, no estilo Socialite. Não usam nem dependem do Marketplace do Spelt. A ordem de implementação está em aberto (§16).

## 11. API do produto para os usuários?

**Não** (nesta versão). O webhook de saída e o export cobrem a integração no MVP. Se um dia houver API, ela usa o módulo opcional de API-keys do kit (§22 da spec) e consome do mesmo saldo de créditos.

## 12. Webhook de saída

**Sim — configurado por Área.** Cada Área aponta para o seu destino, coerente com "Área = time": times diferentes costumam operar CRMs ou funis diferentes. O payload identifica sempre ICP, Área e Empresa de origem.

| Evento | Quando |
|---|---|
| `lead.delivered` | Motor entregou um Lead novo (após o débito do crédito). |
| `lead.accepted` | SDR aceitou o Lead. |
| `lead.discarded` | SDR descartou o Lead (inclui o motivo). |
| `deal.opened` | Negócio aberto a partir de um Lead aceito. |
| `deal.stage_changed` | Negócio mudou de etapa no Pipeline. |
| `deal.won` / `deal.lost` | Negócio ganho ou perdido (perda inclui o motivo). |

Entrega com retentativa, assinatura HMAC e log visível na tela de configuração. É feature **do produto**, distinta dos webhooks Spelt↔produto (§8.2 da spec).

## 13. Módulos opcionais do kit usados

- ☑ **Webhook de saída** — núcleo da integração do produto (§12).
- ☐ API-keys do produto — só se houver API pública.
- ☐ Usage-based metering — **não**. O consumo é crédito do Spelt, não overage cobrado.
- ☐ Media/file — não essencial no MVP (só se Negócio ganhar anexos).
- ☐ Provisionamento de domínio/SSL — não se aplica.
- ☑ **Notificação operacional própria** — in-app + e-mail, **só para eventos de máquina** (§13.1).
- ☐ Magic-link de retorno — não.

### 13.1 Notificação operacional

O produto para sozinho em três situações — saldo zerado, assinatura suspensa, teto de Leads em aberto atingido — e o cliente pode não abrir a plataforma naquele dia. Banner na tela não alcança quem não entrou; por isso o módulo é ligado.

| Evento | Canal |
|---|---|
| `icp.paused.no_credit` — saldo zerou, ICPs pararam | in-app + e-mail |
| `account.limit.managed_lead` — teto de Leads em aberto atingido, entrega parada | in-app + e-mail |
| `icp.no_delivery` — ICP ativo sem entregar há N dias (filtro restritivo ou cold start) | in-app + e-mail |
| `webhook.failing` — endpoint da Área falhando repetidamente | in-app + e-mail |
| `lead.delivered` — leads novos aguardando triagem | in-app (digest diário opcional por e-mail) |

**Fronteira:** é notificação **operacional**, sobre o estado da máquina. Cobrança, fatura, vencimento, mudança de plano e qualquer comunicação comercial continuam no **portal Customer do Spelt** (§14 da spec) — o produto não duplica isso.

## 14. Infra própria (fora do kit)

**Sim.** É o centro de custo e o diferencial do produto. Toda ela é **compartilhada**, fora do ciclo de conta e fora do kit.

### 14.1 Base de dados de alto volume — MongoDB

A base `Empresa CNPJ` e o `Perfil Enriquecido` vivem em **MongoDB**, não no MySQL do kit. Volume, formato semi-estruturado (sócios, estabelecimentos, presença digital, decisores) e o padrão de consulta do motor (filtro multi-campo sobre dezenas de milhões de documentos) pedem isso. O MySQL do kit segue guardando **só** os dados da conta: Empresa, Área, Produto, ICP, Lead, Contato, Negócio, Pipeline.

### 14.2 Ingestão da Receita Federal — worker dedicado

**Mesmo código-base, worker próprio.** A ingestão não disputa fila com a operação: roda em worker separado, com sua própria concorrência e seu próprio limite de recursos.

| Etapa | Função |
|---|---|
| `rfb:download` | Baixa o release público, arquivo a arquivo, retomável. |
| `rfb:extract` | Descompacta e particiona. |
| `rfb:import` | Importa por tabela (empresas, estabelecimentos, sócios, Simples, CNAEs, municípios, naturezas jurídicas, qualificações). |
| `rfb:consolidate` | Monta o documento `Empresa CNPJ` consolidado por `cnpj_base`. |

**Não há urgência de frescor.** A ingestão pode se arrastar por dias; o que importa é a base **existir em volume interessante**. O processo é retomável, idempotente por `cnpj_base` e desenhado para rodar em ritmo baixo e contínuo, sem janela de manutenção.

### 14.3 Enriquecimento — sob demanda, Service fatiado em partes

**Princípio econômico: custo único, venda múltipla.** Um CNPJ é enriquecido **uma vez** e entregue a quantos Contratantes o tiverem no ICP — A, B, C, D. O custo é por CNPJ; a receita é por entrega. É a diluição que sustenta "1 crédito = 1 lead".

**A demanda puxa o enriquecimento.** O sistema **não** enriquece a base inteira: enriquece o que os ICPs ativos estão pedindo. Se há ICPs demandando o CNAE 11111, a fila de enriquecimento passa a puxar CNPJs daquele CNAE. Nenhum ciclo é gasto em faixa da base que ninguém prospecta — e a fila se reorienta sozinha conforme a carteira muda.

**Re-alimentação a cada 90 dias.** CNPJ já enriquecido volta para a fila após 90 dias, para recuperar contato, site, redes e decisores que mudaram.

Um Service de enriquecimento com **pipeline de processadores pequenos e independentes**. Quanto mais granular, melhor: cada parte tem entrada e saída próprias, falha isolada e retentativa própria.

| Parte | O que produz |
|---|---|
| `WebsiteDiscovery` | Descobre o domínio da empresa (Receita, busca, redes). |
| `WebsiteCrawler` | Coleta páginas-chave (home, sobre, contato, time). |
| `ContactExtractor` | Extrai e-mails e telefones das páginas coletadas. |
| `EmailValidator` | Validação do e-mail. **Não é engine própria:** consome o **MailValidation** (§10.1), produto irmão do mesmo kit. |
| `PhoneValidator` | Formato, tipo e operadora. |
| `SocialProfileFinder` | LinkedIn, Instagram, Facebook, X. |
| `DecisionMakerFinder` | Nomes e cargos de decisores (página de time, redes, quadro societário). |
| `TechStackDetector` | DNS, SSL, headers, CMS, analytics. |
| `OfferProfiler` | O que a empresa **vende** (CNAE + conteúdo do site). |
| `ConsumptionProfiler` | O que a empresa **compra** — `probable_needs` em CAPEX / COGS / OPEX / logística, derivado do CNAE e das categorias. |
| `LeadScorer` | Score 0–100 e categoria A/B/C/D. |

Nenhuma parte é obrigatória para o documento existir: o Perfil Enriquecido é incremental e sempre consultável no estado em que estiver.

> **Custo de partida (cold start).** ICP que pede um CNAE ainda não enriquecido não entrega no primeiro ciclo — a fila precisa produzir antes. Isso torna o **tempo até o primeiro lead** uma métrica de produto, não um detalhe: o builder de ICP precisa dizer ao Contratante que a primeira entrega pode demorar, e a fila deve priorizar ICP recém-criado sem entrega nenhuma.

> **`EmailValidator` é dependência externa, não engine.** A validação de e-mail é feita pelo **MailValidation** ([`mail-validation.md`](mail-validation.md)) — mesmo grupo, mesma casa. Evita duplicar pool de IPs, reputação de envio e engine de TELNET/SMTP em dois produtos. Ver §10.1 para o contrato.

### 14.4 Motor de match

Varre **todos** os ICPs ativos da plataforma **a cada 15 minutos**; cada ICP tem seu próprio `next_verification_at`, calculado a partir da taxa diária, de modo que a entrega se distribua ao longo do dia em vez de despejar a cota de uma vez. Para cada ICP: monta a consulta a partir das regras (campo · operador · valor · `and`/`or`), soma as regras `OR` derivadas do perfil de consumo do Produto, aplica **score mínimo**, **janela de dedup**, **taxa diária do ICP** e **saldo da conta**; entrega, debita e copia os Contatos.

A tese do match: do CNPJ do alvo → CNAE → o que aquele CNAE indica que a empresa **precisa comprar** (`probable_needs`) → cruza com o que o Produto do Contratante **vende**.

### 14.5 Lead scoring

Score 0–100 composto, com categorias A (80+) / B (60–79) / C (40–59) / D (<40). Componentes previstos: qualidade e quantidade de decisores, porte da empresa, completude dos dados e aderência ao ICP. O score é **filtro** — o ICP define o mínimo (**default 60**, categoria B) e abaixo dele o motor não entrega e não gasta crédito (§4). Os pesos exatos estão em aberto (§16).

### 14.6 Observabilidade

Custo por lead entregue, taxa de falha por parte do enriquecimento, latência do ciclo de match, cobertura da base, taxa de descarte por ICP e por motivo.

## 15. Mapa de acoplamento

- **Spelt entrega:** identidade e SSO · billing · planos e entitlements · **Créditos** (`credit.granted` / `credit.expired`, incluindo compra avulsa e cortesia de trial) · `change-plan` e trava de downgrade · gate de acesso · eventos de ciclo de vida do Account.

- **Produto constrói:** base da Receita Federal (MongoDB) · pipeline de enriquecimento · motor de match · lead scoring · Empresa/Área/Produto/ICP/Lead/Contato/Negócio/Pipeline · Kanban · export · webhook de saída por Área · pacing e consumo local de créditos · Lista de Supressão global · papéis e permissões · wizard de onboarding · isolamento por `account_id`. **Não constrói:** validação de e-mail — consome o MailValidation (§10.1).

- **Kit facilita:** shell e design system da Platform · filas e workers · **credit-sync** · leitura e snapshot de entitlements · hooks `provision` / `onPlanChanged` / `teardown` · `spelt:reconcile` · gate.

- **Kit absorve:** `credit-sync` como núcleo (§8.6) · **webhook de saída** e **notificação operacional própria** como módulos opcionais (§22) — a Fábrica de Lead é o caso que valida o módulo, agora com o grão "por sub-entidade da conta" (Área), não só por Account.

## 16. Decisões em aberto

> Já **decididos** e escritos nas seções correspondentes: trial (7 dias / 35 leads / 5 por dia), taxa padrão por regra (40% do ciclo), janela de dedup (90 dias), cadência do motor (15 min), score mínimo default (60), papéis (§6.1), notificação operacional (§13.1) e wizard de onboarding (§3).

1. **Pesos do lead scoring:** distribuição entre decisores, porte, completude de dados e aderência ao ICP.
2. **Valores dos entitlements por plano:** Empresas, Áreas, Produtos, ICPs, usuários e Leads em aberto para cada degrau.
3. **Expiração dos créditos no plano do Spelt.** Com a taxa padrão dos 40% o cliente deixa ~60% do pacote na mesa — a sobra só vira margem se **expirar no fim do ciclo**. Se acumular indefinidamente, o consumo tende a 100% e a folga desaparece. É configuração de plano no Spelt (§4), mas é dela que depende a economia desenhada na §14.3.
4. **Teto de custo por entrega:** medir o custo real por CNPJ enriquecido e por lead entregue, e decidir se o pipeline precisa de freio explícito (parar de enriquecer acima de um custo, entregar com perfil parcial) ou se a diluição da §14.3 basta.
5. **Tier do MailValidation** por etapa do enriquecimento: *Existência* no perfil e *Uso* só no contato a entregar — e o teto de custo disso (item 4).
6. **Fonte dos decisores:** quadro societário da Receita, scraping de página de time, redes sociais ou fornecedor terceiro — com implicação direta na §17.
7. **Taxonomia dos motivos de descarte:** lista fechada, para virar sinal estruturado.
8. **Realimentação do ICP:** como (e se) o motivo de descarte ajusta automaticamente as regras ou o score no Estágio 2.
9. ~~**Downgrade de `plan-feature-managed-lead`**~~ — **decidido: soft-cap** (§8). É a única feature cujo número sobe sem ação do cliente; bloquear o downgrade por ela faria o cliente perseguir um alvo que o próprio produto empurra. Revisitar se aparecer abuso.
10. **Volume mínimo de base** para lançar. Com enriquecimento sob demanda (§14.3), o que precisa estar completo no dia 1 é a base **bruta** da Receita; a base **enriquecida** cresce puxada pelos ICPs. Definir o piso de cobertura bruta e o tempo aceitável de cold start.
11. **TTL por parte do perfil.** A política geral é **90 dias** (§14.3); avaliar se alguma parte merece cadência própria — domínio muda menos que telefone, decisor muda mais que CNAE.
12. **Ordem dos conectores** do Estágio 2.
13. **Exclusividade de lead:** confirmado que **não há** reserva entre Contratantes (o mesmo CNPJ pode ser entregue a concorrentes). Revisitar se virar objeção comercial recorrente.

## 17. Compliance / operação

### Papéis e base legal

- A Fábrica de Lead é **controladora** da base `Empresa CNPJ`, do `Perfil Enriquecido` e do Lead Scoring — ela define a finalidade e os meios do tratamento. Isso é diferente da maioria dos produtos do kit, onde o cliente é o controlador e o produto é operador.
- A partir da entrega, o Contratante passa a ser controlador dos **Contatos copiados para a conta dele** e responde pelo uso que fizer deles. A relação e as responsabilidades de cada lado precisam estar no contrato/DPA.
- ⚠️ **"Base legal B2B" não existe na LGPD.** A LGPD não tem isenção para relações entre empresas. Dado de CNPJ não é dado pessoal, mas **nome de sócio, quadro societário, CPF, e nome/cargo/e-mail/telefone de decisores são**.
- A hipótese aplicável é o **legítimo interesse** (art. 7º, IX), que exige: **LIA** (avaliação de legítimo interesse) documentada com teste de balanceamento, transparência ativa sobre a origem dos dados, e garantia efetiva do **direito de oposição** (art. 18).
- Classificação por operação, conforme o [guia da ANPD sobre agentes de tratamento](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-para-definicoes-dos-agentes-de-tratamento-de-dados-pessoais-e-do-encarregado).

### Supressão — Lista global + expurgo retroativo

- Titular que exercer oposição ou eliminação entra na **Lista de Supressão global** (hash de e-mail, telefone e CPF). O pipeline de enriquecimento **nunca reprocessa** e o motor **nunca entrega** quem está nela.
- Além do bloqueio prospectivo, roda **expurgo retroativo**: varredura dos Contatos já copiados para as contas dos Contratantes, eliminando o dado e notificando o Contratante do expurgo.
- A supressão é **global e permanente**: vale para todos os Contratantes, presentes e futuros.
- **Canal público do titular**, acessível sem login, com prazo de resposta e registro do atendimento.

### Origem e coleta

- Registrar **procedência por campo** (Receita / site da empresa / rede social / inferência) — é o que permite responder ao titular de onde veio o dado.
- Scraping respeita `robots.txt` e termos de uso, com rate-limit; sem burlar autenticação ou paywall.
- Validação de e-mail não envia mensagem; se usar sondagem SMTP, tratar reputação de IP como infra crítica (ver §10.1).
- Reavaliar periodicamente a licitude de campos sensíveis da base pública (e-mail, telefone e dados de sócio), que já foram objeto de questionamento.

### Retenção e auditoria

- Dados da conta: somente leitura por 30 dias após o cancelamento, eliminação em até 90 (§7).
- Perfil Enriquecido: TTL de re-verificação por parte (§16 item 13); dado que não é mais reencontrável na fonte é descartado, não congelado.
- **Log de auditoria** de entrega de Lead, acesso a Contato e **exportação** (quem, o quê, quando) — é o registro que sustenta a resposta a um titular ou à ANPD.

### Contrato com o Contratante

- Finalidade permitida: prospecção comercial própria.
- Proibição expressa de revenda ou redistribuição da base.
- Obrigação de honrar opt-out recebido diretamente e de manter base legal própria para o disparo.
- Responsabilidade do Contratante pelo conteúdo, frequência e canal das comunicações que enviar.

### Segurança

- Isolamento obrigatório por `account_id`; criptografia em trânsito e em repouso.
- Segredo de webhook por Área, com assinatura HMAC.
- Controle de acesso por função e menor privilégio; segredos e credenciais criptografados.
- Backup, restauração, resposta a incidentes e comunicação de violações, em linha com o [Guia de Segurança da Informação da ANPD](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia_seguranca_da_informacao_para_atpps___defeso_eleitoral.pdf) e a [Resolução CD/ANPD nº 2](https://www.gov.br/anpd/pt-br/acesso-a-informacao/institucional/atos-normativos/regulamentacoes_anpd/resolucao-cd-anpd-no-2-de-27-de-janeiro-de-2022).

---

## Mecânica do produto (extras — específico do produto)

- **O modelo Ads.** O ICP se comporta como uma campanha: tem taxa diária, filtros e corte de qualidade. O sistema já nasce fatiado — **40% do pacote distribuído no ciclo** (§4), contra os 100% que o saldo permitiria. No dia 1 o Contratante recebe, analisa, ajusta as regras e o score mínimo; no dia 2 recebe melhor. A calibração é incremental e é isso que transforma "lista de empresas" em "entrega confiável".

- **Confiança é o produto.** Como o crédito queima sem ação do Contratante, a plataforma precisa provar o tempo todo que está gastando bem: saldo e ritmo visíveis no dashboard, score explicado no Lead, motivo de descarte capturado, e ICP que pausa sozinho quando o saldo acaba em vez de continuar consumindo.

- **Semântica do Lead:** **compartilhado** entre Contratantes — não há exclusividade nem reserva. Dentro da mesma conta, o mesmo CNPJ só volta depois da janela de dedup (§4).

- **Motor de match:** do CNPJ do alvo → CNAE → o que aquele CNAE indica que ele **precisa comprar** → cruza com o que o **Produto** do Contratante vende, via os itens CAPEX/COGS/OPEX e o `probable_needs` das categorias.

- **Triagem como sinal:** `Descartado` carrega motivo e é o insumo de calibração do ICP. `Arquivado` é triagem neutra — o SDR viu, não é lixo, não vai trabalhar agora; libera vaga no teto sem virar sinal negativo para a engine.

- **Pipeline default:** o sistema entrega um funil pronto por Área. A maioria dos clientes permanece nele; a edição existe para quem tem processo próprio.

- **Fora do MVP:** conectores nativos de CRM, enriquecimento de CSV do cliente, API pública, reserva/exclusividade de lead, aprendizado automático a partir do descarte, templates de ICP.

---

## Aprendizados da v1 (descontinuada)

A v1 vive em `~/Environment/Dev/Projects/fabricadelead.com.br/backup/20260903/` e serve como **aprendizado, não como código**. O que ela já provou e vale reaproveitar como desenho:

1. **Gramática de regra do ICP:** `field · field_type · input_value · operator_type · operator_bitwise_type (and|or) · status`. Operadores: `equal`, `not_equal`, `greater_than`, `less_than`, `contains`, `not_contains`.
2. **Vocabulário de filtro pronto:** o enum `IcpFieldType` já organizava 30+ campos em seis famílias — Perfil de Consumo (`CONSUMES_*`), Perfil de Oferta (CNAE, categoria), Cadastro e Porte, Localização, Presença Digital e Dados Inferidos — cada campo com `label()`, `fieldType()` e `inputType()` para gerar a UI do builder.
3. **O match, concretamente:** as `probable_needs` do catálogo do Produto (`consumable_inputs`, `logistics_and_storage`, `machinery_and_equipment`, `services_and_technology`) eram convertidas em regras `OR` sobre os campos `CONSUMES_*` do alvo e mergeadas nas regras do ICP. É o CAPEX/COGS/OPEX da §14 em execução.
4. **Shape do perfil:** `cnpj_base`, `company`, `establishments`, `partners`, `simple_data`, `summary{offer_profile, consumption_profile}`, `digital_presence`, `infrastructure`, `decision_makers`, `scoring`.
5. **Limites operacionais reais:** 350 leads/dia por ICP como default, teto de 50k leads por ICP, ciclo de 1h por ICP (`next_verification_at`), lotes de 2.000 na varredura. Baseline confrontado com os defaults da v2: taxa por regra (40% do ciclo), varredura de 15 min, sem teto absoluto por ICP.
6. **CRM:** `Pipeline → Stage → Deal` (+ notas e histórico de etapa) e `Contact` com `email_is_valid` / `phone_is_valid` — confirmando que validação de contato precisa ser campo de primeira classe, não anexo.
7. **Lead scoring:** componentes de decisores, porte, qualidade de dados e aderência ao ICP, com categorias A/B/C/D. A v1 usava o score como rótulo; **a v2 usa como filtro de entrega** (§4).

---

## Autoavaliação (rubrica)

✅ **§0 Cartão de identidade** preenchido e coerente com a §4 (misto, não recorrente puro).
✅ **Cobrança e quota** mapeadas aos Créditos e entitlements do Spelt, sem inventar primitivo — com o **momento do débito** e o comportamento a saldo zero explícitos.
✅ **Fronteira Spelt × kit × produto** explicitada na §15.
✅ **Provisionamento e ciclo de vida** definidos na §7, com suspensão distinta de cancelamento e prazos concretos de retenção.
✅ **Downgrade** definido **por feature** na §8, com a exceção justificada.
✅ **Módulos opcionais** identificados na §13.
✅ **Infra própria** isolada e nomeada na §14 — MongoDB, worker da Receita, enriquecimento **sob demanda** e fatiado, motor e scoring, com o princípio econômico (custo único, venda múltipla) explícito.
✅ **Decisões em aberto** explicitadas na §16 (13 itens), todas conhecidas.

**Conclusão:** escopo do MVP **fechado**. Os itens da §16 são pendências de calibração, de números de plano e de escolha de fornecedor — não ambiguidades escondidas no escopo. As duas de maior impacto são a **expiração dos créditos (item 3)**, de que depende toda a margem desenhada na §14.3, e a **fonte dos decisores (item 6)**, que puxa direto a §17.
