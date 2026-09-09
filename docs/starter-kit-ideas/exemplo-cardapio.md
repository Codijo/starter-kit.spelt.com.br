# Cardápio — Brief de Produto *(exemplo fictício)*

> ⚠️ **Produto inventado**, escrito para servir de exemplo preenchido do
> [`TEMPLATE.md`](TEMPLATE.md). Não existe e não está no ar.
>
> **Por que este exemplo:** exercita o caminho **capacity-only** — sem créditos
> nenhum —, com renderização pública e domínio próprio do cliente. O par dele é
> [`exemplo-transcreve.md`](exemplo-transcreve.md), que é baseado em créditos.

---

## 0. Cartão de identidade

| Eixo | Este produto |
|---|---|
| Cobrança | recorrente |
| Quota | **limites de capacidade** (sem créditos) |
| Provisionamento por conta | leve |
| Infra própria | sim — renderizador público, domínio/SSL, CDN de imagens |
| Superfície | cliente final (SSO) + **site público do restaurante** |
| Modelo de acesso | free tier de 1 cardápio com 20 itens |

## 1. O que é

Cardápio digital para restaurantes. O dono monta os itens no painel e recebe uma
página pública com QR Code, que atualiza preço e disponibilidade na hora — sem
reimprimir nada.

## 2. Atores & glossário

| Termo | Significado | Mapeia a |
|---|---|---|
| Restaurante | quem assina | **Account = Spelt Tenant** |
| Operador | quem edita o cardápio | User da Account |
| Unidade | uma loja física | entidade do produto |
| Cardápio | um menu publicável | entidade do produto |
| Comensal | quem lê pelo QR Code | **não tem login** |

## 3. Fluxo ponta a ponta

1. Compra no Spelt → webhook `subscription.activated` → **provisionamento leve**
2. Entra pelo **SSO** e cria a primeira Unidade
3. Monta o cardápio → publica → recebe QR Code e URL pública
4. O **gate** confere os limites do plano ao criar Unidade ou item
5. Comensal abre a página — sem autenticação, servida pelo renderizador

## 4. Cobrança, créditos & acesso

- **Usa créditos?** **Não.** É capacity-only: o cliente paga pelo tamanho da
  operação, não pelo uso. Seguindo para a §5.
- **Modelo:** recorrente puro, mensal ou anual
- **Acesso:** free tier permanente com 1 cardápio e 20 itens — sem cartão

> Este é o caso que o template prevê em "se o produto é só capacidade, diga isso
> e pule os itens de crédito". Nenhum evento `credit.*` é consumido.

## 5. Entitlements de capacidade

| Chave | Limita | Enforcement |
|---|---|---|
| `unidades` | lojas por conta | bloqueia a criação |
| `itens_por_cardapio` | tamanho do menu | bloqueia a criação |
| `dominio_proprio` | usar domínio do cliente | esconde a tela |
| `fotos_por_item` | imagens por prato | bloqueia o upload |

## 6. Hierarquia de dados

`Account → Unidade → Cardápio → Seção → Item`. Isolamento por `account_id`; a
página pública resolve pelo slug da Unidade, sem sessão.

## 7. Provisionamento & ciclo de vida

- **Ao ativar:** cria a Unidade padrão e o subdomínio `<slug>.cardapio.app` — leve
- **Up/down:** ao subir, libera domínio próprio; ao descer, ver §8
- **Teardown:** ao cancelar, a página pública mostra "cardápio indisponível" por
  15 dias e depois sai do ar. Os dados ficam 90 dias.

## 8. Downgrade — política

| Feature | Caminho |
|---|---|
| `unidades` | **reconciliar** — mantém a mais antiga ativa, despublica o resto |
| `itens_por_cardapio` | **soft-cap** — nada some; o excedente para de aparecer |
| `dominio_proprio` | **reconciliar** — volta para o subdomínio e remove o certificado |

> Nada é apagado no downgrade. Um restaurante que volta ao plano maior reencontra
> o cardápio como estava — é a diferença entre perder um cliente e reconquistá-lo.

## 9. Superfície da Platform

Unidades · Editor de cardápio · Itens e fotos · QR Code e link ·
Domínio próprio · Configurações

## 10. Integrações externas

iFood e Rappi (**leitura** — importar itens para não redigitar). Sem escrita:
o produto não empurra preço para marketplace.

## 11. API do produto

Não. Nesta fase o valor está na página pública, não em integração programática.

## 12. Webhook de saída

Não.

## 13. Módulos opcionais do kit

☑ media/file (fotos dos pratos) · ☑ provisionamento de domínio/SSL (Cloudflare for SaaS)
☐ API-keys · ☐ webhook de saída · ☐ usage-based metering · ☐ notificação própria · ☐ magic-link

## 14. Infra própria

Renderizador da página pública (cache agressivo, precisa aguentar pico na hora
do almoço) · Cloudflare for SaaS para domínio e certificado do cliente ·
CDN e redimensionamento de imagens · gerador de QR Code.

> Infra leve ainda é infra: mesmo sendo "só um site", a página pública é um
> serviço separado do painel, com outro perfil de carga.

## 15. Mapa de acoplamento

- **Spelt entrega:** assinatura, planos, features de capacidade, SSO, cobrança
- **Produto constrói:** editor, renderizador público, pipeline de imagens, QR
- **Kit facilita:** leitura de features do plano, gate por capacidade, ganchos de
  provisionamento, módulo de domínio/SSL
- **Kit absorve:** o gate por capacidade puro — hoje o kit pressupõe crédito no
  caminho comum, e este produto mostra que o caminho sem crédito merece ser
  igualmente direto

## 16. Decisões em aberto

- A página pública deve ficar no ar durante a inadimplência, ou sai na hora?
- Fotos entram no free tier ou são o primeiro gancho de conversão?
- Domínio próprio no plano intermediário ou só no topo?

## 17. Compliance / operação

Página pública sem dado pessoal do comensal — sem cookie de rastreio por padrão.
Preço exibido é responsabilidade do restaurante; o produto registra quem alterou
e quando, para disputa de consumidor.
