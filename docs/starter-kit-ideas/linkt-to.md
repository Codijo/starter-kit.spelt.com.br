# Template de Brief — Produto do SaaS Kit

> **Para que serve:** preencher **antes** de escrever código de um novo produto sobre o [Starter Kit](../technical/starter-kit.md). Filtra limitações no papel e produz um escopo rico.
> **Como usar:** copie este arquivo para `docs/starter-kit-ideas/<produto>.md` e responda cada `▢`. Cada seção diz *o que responder* e *por que importa* (a costura do kit que ela toca). Os exemplos vêm dos dois casos feitos — **FL** = Fábrica de Lead, **MV** = MailValidation.
> Enquanto houver `▢` sem resposta não-vaga, o escopo **não** está fechado (ver a Rubrica no fim).

---

## 0. Cartão de identidade (classificação rápida)

- Cobrança recorrente.
- Limites de capacidade.
- Leve. O sistema precisará provisionar o domínio/ssl na API do Cloud Flare for SaaS.
- Acredito que não demanda infra robusta.
- Ciente final.

---

## 1. O que é  *(1–3 frases)*
**Guia:** o valor que o cliente final recebe. Nada de gestão — billing/suporte/planos são do Spelt.
> Micro site (não só uma Landing Page), mas sim um 'micro site', guiado, com páginas definidas dentro de um escopo de operação.
> Ex: Um Seller do Spelt quer lançar um sistema (ele cria o sistema inteiro), delega a gestão ao Spelt e delegaria o LinkTO para o site (construção das páginas Home, Planos, Políticas). Também, com add-on, criar LP.

## 2. Atores & glossário
**Guia:** quem usa e como cada termo mapeia ao kit. Regra fixa: **Account = Spelt Tenant**.
▢ _Preencher (termo → significado → mapeia a):_
> Cliente (Spelt->Customer) tem Projeto->Domínio(s)->Páginas->Conteúdo
> Limita por Projetos, Domínios, Páginas. Espaço para Arquivos (imagens, vídeos)

## 3. Fluxo ponta a ponta
**Guia:** da compra ao uso. Marque onde entram SSO, webhook, gate e provisionamento.
> O Cliente compra no site o plano 'Construtor Básico', entra no Painel do Customer e de lá, acessa o pages.linkto.com.br.
> Ao acessar o 'pages', ele cria um projeto (dentro dos limites do plano contratado).
> Adiciona um domínio. O 'produto' provisiona o domínio no Cloud Flare (depois de verificar que o DNS foi apontado)
> As páginas criadas e o 'mini site' carregam da API do produto ou outro projeto em Laravel.
> Cliente gerencia páginas, ve estatísticas (se o plano permitir)

## 4. Cobrança & créditos  *(a costura mais crítica)*
> ex. FL: 1 crédito = 1 lead; recorrente; 1:1; compra avulsa sim.
> ex. MV: 1 crédito = 1 validação; pay-as-you-go; expira; custo por tier (mecânica 1 / existência N / uso M).
> Cobrança recorrente baseado no plano escolhido. 
> Tem Free Tier. Plano 'Freemium'.
> Tem Trial. Trial entrega tudo, por x dias.

## 5. Entitlements de capacidade  *(tetos estáticos)*
**Guia:** o que **não** é crédito. Lidos das features do plano no Spelt (`GET /plan/{id}`, §9). Liste as chaves:
▢ _Preencher (chave → o que limita → enforcement):_
> Projetos, Domínios, Páginas, Espaço em GB (arquivos), Relatórios Avançados (Sim|Não)

## 6. Hierarquia de dados
**Guia:** sub-entidades do produto dentro da conta (não é tenancy do Spelt). Isolamento por `account_id`.
▢ _Preencher (Account → … → …):_
> Account -> Projeto -> Domínios do Projeto

## 7. Provisionamento & ciclo de vida
**Guia:** o kit dá os ganchos (`provision`/`onPlanChanged`/`teardown`) por fila; o produto implementa (§10).
- Provisiona domínios no Cloud Flare for SaaS. O downgrade precisa prever que não pode ter domínio próprio, por exemplo.

## 8. Downgrade — política
- Provisiona domínios no Cloud Flare for SaaS. O downgrade precisa prever que não pode ter domínio próprio, por exemplo.

## 9. Superfície da Platform (telas)
**Guia:** deriva o shell do portal Customer. Liste as telas do produto.
▢ _Preencher:_
> Projetos, Domínios, Páginas, Lista de Arquivos, Relatórios, Visitantes, Leads, Formulários

## 10. Integrações externas
**Guia:** conectores externos são **do produto** (estilo Socialite), não o Marketplace do Spelt.
> Google Analytics, Formulário de Inscrição (Mailchimp, ex)

## 11. API do produto para os usuários?
**Guia:** se sim, o produto emite **chaves próprias** (módulo opcional §22, herdável do `dev_apis`). Consome do mesmo saldo de créditos?
> Não tem API.

## 12. Webhook de saída?
> Não.

## 13. Módulos opcionais do kit usados
**Guia:** nenhum é padrão (kit mínimo). Marque o que este produto liga:
- usage-based metering
- media/file

## 14. Infra própria (fora do kit)
**Guia:** o motor que entrega o valor. Alto nível + por que fica fora do kit e do ciclo de conta.
> Não.

## 15. Mapa de acoplamento *(fechamento)*
**Guia:** preencher as 4 linhas força a clareza da fronteira.
- ▢ **Spelt entrega:**
- ▢ **Produto constrói:**
- ▢ **Kit facilita:**
- ▢ **Kit absorve** (se surgir algo novo):

## 16. Decisões em aberto


## 17. Compliance / operação


---

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
