# Psst — Brief de Produto

> **Status:** brief do 1º produto sobre o Starter Kit, no formato do [`TEMPLATE.md`](TEMPLATE.md).
> **Não é código nem parte do Spelt.** Doc de staging — destino a definir.
> **Perfil:** provisionamento leve · créditos + capacidade · misto (recorrente + avulso) · trial · cliente final.
> **Data:** 2026-08-28.

---

## 0. Cartão de identidade

| Eixo | Este produto |
|---|---|
| Cobrança | **misto** (recorrente + compra avulsa) |
| Quota | **ambos** — créditos de geração (consumível) + limites de capacidade |
| Provisionamento por conta | leve |
| Infra própria do produto | **sim** — engine de geração/avaliação de ganchos (IA) + renderizador de previews |
| Superfície | cliente final (SSO) |
| Modelo de acesso | **trial** (7 dias / 10 créditos) · pago-direto (recorrente). Sem free tier permanente |

---

## 1. O que é
O Psst ajuda times de marketing a aumentar as chances de suas mensagens serem abertas e lidas, criando e aprimorando os primeiros elementos que o público vê — como assuntos, primeiras linhas, preheaders e notificações. O cliente recebe ganchos mais claros, relevantes e atrativos, adaptados à sua marca, público, intenção e canal de comunicação.

## 2. Atores & glossário

| Termo | Significado | Mapeia a |
|---|---|---|
| **Contratante** | Empresa ou profissional que assina e utiliza o Psst | **Account = Spelt Tenant** (conta pagante) |
| **Usuário** | Pessoa do time de marketing ou vendas que opera o Psst | usuário vinculado ao **Account** |
| **Marca** | Contexto salvo com informações, posicionamento e tom de voz da marca | entidade do produto (N por Account) |
| **Persona** | Perfil do público que deverá receber a comunicação | entidade do produto, vinculada à Marca |
| **Campanha / Briefing** | Intenção, oferta e contexto informados para gerar os ganchos | entidade do produto |
| **Canal** | Meio para o qual o gancho será adaptado: e-mail, WhatsApp, SMS ou Chrome | atributo da geração |
| **Gancho** | Assunto, primeira linha ou texto curto criado para conquistar a atenção inicial do destinatário | entidade do produto |
| **Preview** | Simulação visual de como o gancho será exibido no canal selecionado | representação do Gancho |
| **Geração** | Solicitação que produz uma ou mais opções de gancho e seus respectivos previews | entidade do produto |
| **Crédito (Geração)** | Unidade consumida a cada solicitação de novos ganchos, independentemente da quantidade de variações entregues | **Crédito do Spelt** |

## 3. Fluxo ponta a ponta

1. Contratante escolhe um plano e conclui a compra no Spelt.
2. Pagamento aprovado → **provisionamento automático** do Account, plano e créditos.
3. Usuário entra no Psst por **SSO**.
4. Cadastra uma **Marca**, informando contexto, posicionamento e tom de voz.
5. Cadastra ou seleciona uma **Persona** vinculada à Marca.
6. Cria uma **Campanha / Briefing**, informando intenção, oferta, contexto e chamada desejada.
7. Seleciona os **canais** nos quais deseja simular a comunicação: e-mail, WhatsApp, SMS e/ou Chrome.
8. Antes da geração, o sistema aplica o **gate de créditos e assinatura**.
9. Com acesso liberado, o sistema consome 1 crédito e gera diferentes opções de **Gancho** adaptadas aos canais escolhidos.
10. O usuário visualiza cada gancho em um **Preview**, simulando como a mensagem aparecerá na caixa de entrada ou notificação.
11. O usuário pode copiar, salvar, comparar ou solicitar novas variações.
12. Geração e ganchos ficam salvos no histórico da Marca para reutilização e consulta.
13. Acabaram os créditos → usuário faz upgrade ou compra créditos adicionais no Spelt.
14. **Webhook:** não participa do fluxo do MVP; será introduzido posteriormente nas integrações com plataformas de campanha.

## 4. Cobrança, créditos & acesso

- **Usa créditos consumíveis?** Sim.
- **1 crédito =** 1 solicitação de geração, com quantidade predefinida de ganchos e previews para até quatro canais: e-mail, WhatsApp, SMS e Chrome.
- **Recorrente, pay-as-you-go ou misto?** Misto — o plano concede créditos recorrentes, com opção de compra avulsa.
- **Créditos expiram?** Conforme o grant recebido do Spelt; não é uma regra controlada pelo Psst.
- **Consumo:** 1:1 — uma solicitação de geração consome 1 crédito, independentemente da quantidade de canais selecionados ou variações entregues.
- **Nova geração:** solicitar outras variações ou alterar o briefing e gerar novamente constitui uma nova operação e consome mais 1 crédito.
- **Compra avulsa antes de renovar?** Sim — pagamento único no Spelt gera um novo `credit.granted`.
- **Modelo de acesso:** trial de 7 dias com 10 créditos de geração. Sem free tier permanente. Após o trial, é necessário contratar um plano ou adquirir créditos.
- **Gate:** antes de cada geração, o produto verifica se o Account está ativo e possui saldo local disponível. Visualizar, copiar ou consultar ganchos já gerados não consome créditos.
- **Encaixe:** o Spelt concede e expira quotas por meio de `credit.granted` e `credit.expired`; o Psst administra somente o consumo e o saldo local, conforme §8.6 da spec.

## 5. Entitlements de capacidade

| Chave | O que limita | Enforcement |
|---|---|---|
| `plan-feature-brand` | Quantidade de Marcas que podem ser cadastradas no Account | Ao criar ou reativar uma Marca |
| `plan-feature-user` | Quantidade de usuários vinculados ao Account | Ao convidar ou reativar um usuário |
| `plan-feature-persona` | Quantidade de Personas salvas em cada Marca | Ao criar ou duplicar uma Persona |
| `plan-feature-concurrent-generation` | Quantidade máxima de gerações que podem ser processadas ao mesmo tempo pelo Account | Antes de enviar uma geração para processamento |
| `plan-feature-history-retention` | Período pelo qual campanhas, ganchos e previews permanecem disponíveis no histórico | Em rotina de expiração e ao consultar o histórico |

- Os valores são lidos das features do plano no Spelt por meio de `GET /plan/{id}`, conforme §9.
- Alterações de plano devem atualizar os limites aplicáveis ao Account.
- Redução de plano não apaga imediatamente entidades existentes: bloqueia novas criações enquanto o Account estiver acima do limite.
- Créditos de geração não são entitlements de capacidade e continuam sendo tratados pelo módulo de Créditos do Spelt.

## 6. Hierarquia de dados

**Hierarquia principal:**

Account → Marca → Campanha / Briefing → Geração → Gancho → Preview

**Entidades relacionadas:**

- Account → Usuário
- Marca → Persona
- Campanha / Briefing → referencia uma Marca e uma ou mais Personas
- Geração → referencia os canais selecionados
- Gancho → pertence a uma Geração e a um Canal
- Preview → representa um Gancho no dispositivo e canal correspondente

Todas as entidades do produto são isoladas por `account_id`. O Psst nunca permite consultar, alterar ou relacionar dados pertencentes a Accounts diferentes.

## 7. Provisionamento & ciclo de vida

- **Ao ativar:** leve — cria o registro local do Account, configura preferências padrão e sincroniza plano, entitlements e status de acesso. A infraestrutura de geração e previews é compartilhada entre todos os Accounts.
- **Recursos dedicados:** nenhum no MVP. Não são criadas filas, modelos ou infraestrutura exclusiva por Account.
- **Up/down:** executa `onPlanChanged`, sincroniza os novos entitlements e ajusta imediatamente os tetos de capacidade.
- **Downgrade:** não remove dados automaticamente. Caso o Account esteja acima de um novo limite, preserva as entidades existentes, mas bloqueia novas criações até que volte ao limite permitido.
- **Suspensão:** bloqueia novas gerações, alterações e convites. O histórico permanece disponível em modo somente leitura durante o período definido pela política comercial.
- **Cancelamento:** interrompe novas gerações e desativa os usuários do Account. Dados são mantidos pelo período de retenção definido pela política do produto antes da exclusão definitiva.
- **Teardown:** revoga acessos, cancela processamentos pendentes e agenda a remoção dos dados após o período de retenção. Não afeta a infraestrutura compartilhada.
- Os handlers `provision`, `onPlanChanged` e `teardown` são processados por fila e devem ser idempotentes, conforme §10.

## 8. Downgrade — política

| Feature | Política | Comportamento |
|---|---|---|
| `plan-feature-brand` | **(a) Bloquear no Spelt** | Impede o downgrade quando a quantidade de Marcas ativas for maior que o limite do plano-alvo. O uso atual é reportado via `PUT .../subscription/{id}/feature-usage`. |
| `plan-feature-user` | **(a) Bloquear no Spelt** | Impede o downgrade enquanto houver mais usuários ativos que o permitido pelo plano-alvo. |
| `plan-feature-persona` | **(c) Soft-cap** | Preserva as Personas existentes, mas bloqueia a criação e duplicação em Marcas que estejam acima do novo limite. |
| `plan-feature-concurrent-generation` | **(c) Soft-cap** | Não interrompe gerações que já estejam em andamento. O novo limite é aplicado às próximas solicitações. |
| `plan-feature-history-retention` | **(b) Reconciliar** | No `onPlanChanged`, identifica itens mais antigos que o novo período de retenção e agenda sua expiração conforme a política de carência do produto. |

- O Psst reporta a utilização de `plan-feature-brand` e `plan-feature-user` ao Spelt por meio de `PUT /seller/external/v1/subscription/{id}/feature-usage` (body `{"feature_usage": {"<slug>": <int>}}`, replace completo).
- O Spelt impede o downgrade quando o uso estrutural estiver acima dos limites do plano-alvo.
- Nenhuma Marca, Persona, Campanha ou Geração é apagada imediatamente durante o downgrade.
- Processamentos já iniciados são concluídos normalmente.
- Para efetuar um downgrade bloqueado, o cliente deve primeiro desativar Marcas ou remover usuários excedentes.

## 9. Superfície da Platform (telas)

**Início / Dashboard** · **Nova Geração** · **Preview Multicanal** · **Marcas** · **Personas** · **Histórico de Gerações** · **Detalhe da Geração**

| Tela | Função |
|---|---|
| **Início / Dashboard** | Apresenta saldo de créditos, gerações recentes, Marcas cadastradas e atalho para uma nova geração. |
| **Nova Geração** | Seleção de Marca e Persona, preenchimento do briefing, definição da intenção e escolha dos canais. |
| **Preview Multicanal** | Exibe e permite comparar os ganchos simulados em notificações de e-mail, WhatsApp, SMS e Chrome. |
| **Marcas** | Cadastro e edição de contexto, posicionamento, descrição e tom de voz da Marca. |
| **Personas** | Cadastro e edição dos perfis de público vinculados a cada Marca. |
| **Histórico de Gerações** | Lista campanhas, briefings e ganchos já gerados, com filtros por Marca, Persona, canal e data. |
| **Detalhe da Geração** | Exibe briefing, canais, ganchos e previews de uma geração anterior, permitindo copiar ou solicitar novas variações. |

Gestão de usuários, assinatura, plano, créditos e dados da conta utiliza as telas compartilhadas do portal Customer do Spelt, não telas próprias do Psst.

**Fora do MVP:** conectores OAuth, caixa de entrada inteligente, análise histórica de campanhas e geração de criativos para redes sociais.

## 10. Integrações externas

### MVP

- **Nenhum conector externo de campanha.**
- O usuário informa o briefing manualmente e copia os ganchos gerados.
- O provedor de IA utilizado para gerar e avaliar os ganchos é uma dependência interna de infraestrutura, não um conector configurável pelo cliente.

### Estágio 2 — histórico de campanhas

| Integração | Acesso | Uso |
|---|---|---|
| **Mailchimp** | Somente leitura | Ler campanhas, assuntos, preheaders, segmentos e métricas históricas. |
| **ActiveCampaign** | Somente leitura | Ler campanhas, automações, assuntos e métricas históricas. |
| **Brevo** | Somente leitura | Ler campanhas, assuntos e resultados de envio. |
| **RD Station Marketing** | Somente leitura | Ler campanhas e métricas históricas, conforme disponibilidade da API. |

- Autorização por OAuth ou mecanismo equivalente oferecido pelo provedor.
- O Psst não cria, altera nem dispara campanhas nessa etapa.
- Escrita e publicação direta poderão ser avaliadas posteriormente, mas não fazem parte do escopo inicial.

### Estágio 3 — caixa de entrada inteligente

| Integração | Acesso | Uso |
|---|---|---|
| **Provedor de e-mail transacional/inbound** | Recebimento | Criar endereços exclusivos e receber campanhas encaminhadas ao Psst. |
| **WhatsApp Business Platform** | Recebimento via webhook | Receber mensagens de números autorizados para análise. |
| **Provedor de SMS** | Recebimento via webhook | Receber mensagens de teste para análise, se incluído no produto. |

Todos os conectores são implementados e mantidos pelo Psst, no estilo Socialite. Eles não utilizam nem dependem do Marketplace do Spelt.

## 11. API do produto para os usuários?
**Não** (nesta versão).

## 12. Webhook de saída

- **MVP:** não.
- As gerações são iniciadas e consultadas diretamente na interface do Psst, sem necessidade de emitir eventos para sistemas do cliente.
- **Evolução futura:** webhook opcional por **Account**, com possibilidade de filtro por Marca.
- Eventos previstos:
  - `generation.completed` — geração de ganchos concluída.
  - `generation.failed` — geração não pôde ser concluída.
  - `campaign.analysis.completed` — análise de campanha recebida ou importada concluída.
- O webhook de saída só será implementado quando houver API, processamento assíncrono externo ou integração de escrita com ferramentas de campanha.

## 13. Módulos opcionais do kit usados

### MVP

- **Nenhum módulo opcional do kit.**
- ☐ API-keys do produto
- ☐ Webhook de saída
- ☐ Usage-based metering
- ☐ Media/file
- ☐ Provisionamento de domínio/SSL
- ☐ Notificação operacional própria
- ☐ Magic-link de retorno

O consumo do Psst utiliza o módulo padrão de **Créditos do Spelt** e não `usage-based metering`. Os previews são renderizados pela aplicação e não precisam ser armazenados como arquivos no MVP.

### Evoluções futuras

- **Webhook de saída:** quando houver API ou integrações externas assíncronas.
- **Media/file:** quando o produto passar a gerar thumbnails e peças para redes sociais.
- **Notificação operacional própria:** para avisos sobre campanhas recebidas pela caixa de entrada inteligente e análises concluídas.
- **API-keys do produto:** somente se o Psst disponibilizar uma API pública para geração de ganchos.

## 14. Infra própria (fora do kit)

**Sim:** engine de geração e avaliação de ganchos, responsável por combinar contexto da Marca, Persona, intenção, briefing, tom de voz e regras específicas de cada canal.

### Infra compartilhada do produto

- **Orquestração de IA:** montagem de contexto, prompts, validação de respostas e tratamento de falhas do provedor.
- **Engine de avaliação:** pontuação dos ganchos por clareza, relevância, curiosidade, personalização, tamanho e risco de parecer spam ou clickbait.
- **Renderizador de previews:** simulação visual de notificações de e-mail, WhatsApp, SMS e Chrome em diferentes tamanhos de tela.
- **Filas e workers:** processamento assíncrono de gerações, retries, timeouts e controle de concorrência.
- **Persistência do histórico:** armazenamento de briefings, gerações, ganchos, avaliações e previews vinculados ao `account_id`.
- **Observabilidade:** logs técnicos, métricas de uso, erros, latência e custo por geração.

Não existe infraestrutura dedicada por Account no MVP. Toda a engine é compartilhada, com isolamento lógico dos dados por `account_id`.

### Evoluções futuras

- Pipeline de importação e normalização de campanhas e métricas dos conectores.
- Infra de recebimento de e-mails e mensagens por webhook.
- Engine de aprendizado baseada no histórico de desempenho de cada Marca.
- Armazenamento e processamento de thumbnails e peças para redes sociais.

## 15. Mapa de acoplamento

- **Spelt entrega:** identidade e SSO · billing · planos e entitlements · Créditos (`credit.granted` / `credit.expired`, incluindo compra avulsa) · `change-plan` · gate de acesso · eventos de provisionamento e ciclo de vida do Account.

- **Produto constrói:** Marca · Persona · Campanha / Briefing · Geração · Gancho · Preview · engine de geração e avaliação · renderização multicanal · consumo e saldo local de créditos · histórico · isolamento por `account_id` · integrações OAuth e caixa de entrada inteligente nas etapas futuras.

- **Kit facilita:** shell e telas compartilhadas do portal Customer · filas e workers · sincronização de créditos · leitura de plano e entitlements · hooks `provision`, `onPlanChanged` e `teardown`.

- **Kit absorve:** `credit-sync` como parte do núcleo. Nenhum módulo opcional adicional no MVP; futuramente poderá absorver webhook de saída, media/file, API-keys e notificações operacionais próprias.

## 16. Decisões em aberto

1. **Quantidade de variações por geração:** definir quantos ganchos serão entregues por crédito.
2. **Formato multicanal:** confirmar se 1 crédito gera versões para todos os canais ou somente para os canais selecionados.
3. **Critérios de avaliação:** definir pesos de clareza, curiosidade, personalização, urgência, tamanho, clickbait e risco de spam.
4. **Provedor e modelo de IA:** escolher fornecedor principal, fallback, política de retenção e limite de custo por geração.
5. **Fidelidade dos previews:** definir dispositivos, sistemas operacionais, navegadores e tamanhos de tela oficialmente simulados.
6. **Valores dos entitlements:** estabelecer limites de Marcas, usuários, Personas, concorrência e retenção para cada plano.
7. **Retenção de dados:** definir prazo do histórico por plano, carência após cancelamento e período para exclusão definitiva.
8. **Feedback do usuário:** decidir se copiar, favoritar ou escolher um gancho será usado para melhorar recomendações futuras.
9. **Métricas históricas:** definir como o produto distinguirá correlação de causalidade ao recomendar ganchos com base em campanhas anteriores.
10. **Ordem dos conectores:** escolher qual plataforma será a primeira integração após o MVP.
11. **WhatsApp:** decidir entre contato compartilhado, número por Account ou infraestrutura fornecida pelo próprio cliente.
12. **Redes sociais:** decidir se a geração visual continuará como módulo do Psst ou se será tratada como produto separado.

## 17. Compliance / operação

### LGPD e privacidade

- Para dados de conta, cobrança, segurança e operação do serviço, o Psst poderá atuar como **controlador**, conforme a finalidade específica.
- Para dados inseridos pelo cliente em Marcas, Personas, briefings e campanhas, a tendência é o cliente atuar como **controlador** e o Psst como **operador**, tratando os dados conforme suas instruções. A classificação deve ser avaliada por operação, conforme orientação da [ANPD sobre agentes de tratamento](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-para-definicoes-dos-agentes-de-tratamento-de-dados-pessoais-e-do-encarregado).
- Coletar somente os dados necessários para geração, histórico e operação do produto.
- Evitar o envio de dados pessoais sensíveis, listas completas de contatos ou informações desnecessárias ao provedor de IA.
- Formalizar política de privacidade, termos de uso, acordo de tratamento de dados e relação de subprocessadores.
- Disponibilizar mecanismos de consulta, exportação, correção e exclusão dos dados aplicáveis.
- Definir retenção e expurgo para briefings, Personas, campanhas, logs e backups.
- Contratar provedores de IA com condições empresariais adequadas, retenção controlada e não utilização dos dados do cliente para treinamento, quando disponível.
- Avaliar e documentar eventual transferência internacional de dados.

### Segurança

- Isolamento obrigatório por `account_id`.
- Criptografia em trânsito e em repouso.
- Controle de acesso por função e princípio do menor privilégio.
- Segredos, tokens OAuth e credenciais armazenados de forma criptografada.
- Logs de auditoria para acessos, gerações, integrações e exclusões.
- Política de backups, restauração, resposta a incidentes e comunicação de violações.
- Adoção de medidas técnicas e administrativas proporcionais ao risco desde a concepção do produto, em linha com o [Guia de Segurança da Informação da ANPD](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia_seguranca_da_informacao_para_atpps___defeso_eleitoral.pdf).

### Comunicação e responsabilidade

- No MVP, o Psst **não envia campanhas**; apenas gera e simula ganchos. Portanto, não opera reputação de IP, domínio ou número telefônico.
- O cliente é responsável pela base legal, destinatários, opt-out, frequência e conteúdo das comunicações que enviar.
- O produto deve deixar claro que suas avaliações são recomendações e não garantem abertura, clique ou conversão.
- Implementar proteções contra geração de conteúdo fraudulento, discriminatório, enganoso ou voltado a phishing e personificação.
- Quando houver integrações, observar os termos de uso, limites de API e políticas antispam de cada plataforma.
- Na futura caixa de entrada inteligente, aceitar apenas remetentes autorizados e aplicar rate-limit, autenticação, bloqueio de abuso e expurgo de conteúdo recebido.
- Se o Psst se enquadrar como agente de tratamento de pequeno porte, ainda deverá manter medidas essenciais de segurança e um canal para titulares, conforme a [Resolução CD/ANPD nº 2](https://www.gov.br/anpd/pt-br/acesso-a-informacao/institucional/atos-normativos/regulamentacoes_anpd/resolucao-cd-anpd-no-2-de-27-de-janeiro-de-2022).

---

## Mecânica do produto (extras — específico do produto)

- **Entrada da geração:** o usuário seleciona uma Marca e uma Persona, informa intenção, oferta, contexto da campanha e chamada desejada, e escolhe até quatro canais: e-mail, WhatsApp, SMS e Chrome.

- **Unidade de geração:** cada solicitação produz **5 direções criativas de gancho**, adaptadas aos canais selecionados. As adaptações pertencentes à mesma direção mantêm a ideia central, mas respeitam a linguagem e o espaço disponível em cada canal.

- **Saída por canal:**
  - **E-mail:** assunto + preheader/primeira linha.
  - **WhatsApp:** primeira linha visível na notificação.
  - **SMS:** trecho inicial da mensagem.
  - **Chrome:** título + corpo curto da notificação.

- **Preview:** cada saída é renderizada em uma simulação de notificação. O preview considera truncamento, quantidade aproximada de caracteres, nome do remetente e hierarquia visual do canal.

- **Limite do preview:** a simulação é uma aproximação. A exibição final pode variar conforme dispositivo, sistema operacional, aplicativo, navegador, tamanho da tela e configurações do destinatário.

- **Avaliação:** cada gancho recebe uma análise de clareza, relevância, curiosidade, personalização, adequação ao tom, risco de clickbait, risco de parecer spam e aproveitamento do espaço visível.

- **Recomendações:** o sistema explica de forma curta por que cada direção pode funcionar e destaca eventuais problemas, mas não promete abertura, clique ou conversão.

- **Seleção e uso:** o usuário pode comparar, copiar e salvar os ganchos sem novo consumo. A escolha de um gancho pode ser registrada como feedback para futuras recomendações.

- **Regeneração:** alterar o briefing ou solicitar novas direções cria uma nova Geração e consome 1 novo crédito. Apenas trocar a visualização do dispositivo não gera consumo.

- **Consumo:** 1 Geração bem-sucedida = 1 crédito, independentemente da quantidade de canais selecionados. O consumo deve ser idempotente para impedir cobrança duplicada por retry ou atualização da página.

- **Falhas:** uma geração que falhar integralmente não consome crédito ou provoca estorno local automático. Se o resultado for entregue e apenas um preview falhar, o sistema tenta renderizá-lo novamente sem novo consumo.

- **Histórico:** briefing, contexto utilizado, canais, resultados, avaliações e previews ficam vinculados à Marca e à Geração. Alterações posteriores na Marca ou Persona não modificam retroativamente as gerações anteriores.

- **Aprendizado no MVP:** o Psst utiliza o contexto fornecido pelo usuário, mas não afirma aprender com performance real. Recomendações baseadas em abertura e clique entram somente após as integrações históricas do Estágio 2.

- **Fora do MVP:** disparo de mensagens, escrita em plataformas de campanha, caixa de entrada inteligente, números próprios de WhatsApp/SMS, aprendizado baseado em métricas e geração de peças para redes sociais.

---

## Autoavaliação (rubrica)

✅ Cobrança e quota mapeadas aos **Créditos** e entitlements do Spelt, sem primitivo novo.  
✅ Fronteira entre Spelt, kit e produto explicitada no §15.  
✅ Provisionamento e ciclo de vida definidos no §7.  
✅ Downgrade definido por feature no §8.  
✅ Módulos opcionais identificados no §13.  
✅ Infra compartilhada do produto isolada e nomeada no §14.  
✅ Decisões ainda pendentes explicitadas no §16.  
⚠️ Cartão de identidade (§0): precisa estar preenchido no documento final para concluir a rubrica.

**Conclusão:** com o §0 preenchido, o brief pode ser considerado **rico e com o escopo do MVP fechado**. As decisões do §16 são pendências conhecidas de implementação, produto e operação, não ambiguidades escondidas no escopo.
