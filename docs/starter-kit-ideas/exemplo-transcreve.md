# Transcreve — Brief de Produto *(exemplo fictício)*

> ⚠️ **Produto inventado**, escrito para servir de exemplo preenchido do
> [`TEMPLATE.md`](TEMPLATE.md). Não existe e não está no ar. Use como referência
> de *como responder*, não do que construir.
>
> **Por que este exemplo:** exercita o caminho de **créditos consumíveis** com
> custo por operação, API própria e webhook de saída. O par dele é
> [`exemplo-cardapio.md`](exemplo-cardapio.md), que é capacity-only.

---

## 0. Cartão de identidade

| Eixo | Este produto |
|---|---|
| Cobrança | misto — mensalidade com franquia + compra avulsa |
| Quota | créditos consumíveis |
| Provisionamento por conta | leve |
| Infra própria | sim — fila de transcrição, storage de áudio, modelo de fala |
| Superfície | cliente final (SSO) |
| Modelo de acesso | trial de 14 dias com 30 minutos |

## 1. O que é

Transcreve áudio e vídeo em texto com marcação de tempo e separação de quem
fala. O cliente envia um arquivo ou uma URL e recebe o texto pronto para editar,
exportar ou consumir via API.

## 2. Atores & glossário

| Termo | Significado | Mapeia a |
|---|---|---|
| Contratante | quem assina | **Account = Spelt Tenant** |
| Membro | quem envia e edita | User da Account |
| Mídia | um arquivo enviado | entidade do produto |
| Transcrição | o resultado de uma Mídia | entidade do produto |

## 3. Fluxo ponta a ponta

1. Compra no Spelt → webhook `subscription.activated` → **provisionamento leve**
2. Entra pelo **SSO** e cai no painel
3. Envia a mídia → o **gate** confere saldo antes de aceitar
4. Fila processa → debita créditos pela duração real
5. `credit.granted` na renovação repõe a franquia

## 4. Cobrança, créditos & acesso

- **Usa créditos?** sim
- **1 crédito** = 1 minuto de áudio processado, arredondado para cima
- **Modelo:** misto — franquia mensal do plano + avulso quando acaba
- **Expiram?** os da franquia sim, no fim do ciclo; os avulsos não
- **Consumo:** custo por operação — `1×` transcrição simples, `1,5×` com
  separação de interlocutores, `2×` com tradução
- **Compra avulsa:** sim, pacotes de 60, 300 e 1000 minutos
- **Acesso:** trial de 14 dias com 30 minutos, sem cartão

> A tabela de pesos é do produto. O Spelt só sabe **quantos créditos** foram
> consumidos — não o que cada um significa.

## 5. Entitlements de capacidade

| Chave | Limita | Enforcement |
|---|---|---|
| `duracao_maxima_minutos` | tamanho de uma mídia | rejeita no upload |
| `transcricoes_simultaneas` | processamento paralelo | fila segura o excedente |
| `retencao_dias` | quanto tempo a mídia fica guardada | rotina de expurgo |

## 6. Hierarquia de dados

`Account → Pasta → Mídia → Transcrição → Trecho`. Isolamento por `account_id`
em todas as tabelas.

## 7. Provisionamento & ciclo de vida

- **Ao ativar:** cria o bucket da conta e a pasta padrão — leve
- **Up/down:** ajusta `transcricoes_simultaneas` e `retencao_dias`, sem tocar em dados
- **Teardown:** ao cancelar, congela por 30 dias; depois apaga as mídias e
  mantém os textos por mais 60

## 8. Downgrade — política

| Feature | Caminho |
|---|---|
| `duracao_maxima_minutos` | **bloquear no Spelt** — impede antes do upload |
| `retencao_dias` | **reconciliar** — expurgo assíncrono no `onPlanChanged` |
| `transcricoes_simultaneas` | **soft-cap** — a fila desacelera, nada quebra |

## 9. Superfície da Platform

Biblioteca de mídias · Editor de transcrição · Upload · Chaves de API ·
Consumo e saldo · Configurações da conta

## 10. Integrações externas

Google Drive e Dropbox (**leitura** — importar mídia) · Notion (**escrita** —
exportar o texto). Conectores do produto, não do Marketplace.

## 11. API do produto

Sim. Chaves com prefixo `tsc_`, herdadas do módulo `dev_apis`. **Consome do
mesmo saldo** — a origem não muda o custo.

## 12. Webhook de saída

Sim, por **conta**: `transcricao.concluida` e `transcricao.falhou`, para o
cliente disparar o fluxo dele sem ficar consultando.

## 13. Módulos opcionais do kit

☑ API-keys do produto · ☑ webhook de saída · ☑ media/file
☐ usage-based metering · ☐ domínio/SSL · ☐ notificação própria · ☐ magic-link

> Não usa *usage-based metering*: o consumo é debitado de créditos comprados, não
> reportado ao Spelt para cobrar overage.

## 14. Infra própria

Fila de processamento com GPU sob demanda · storage de objetos para as mídias ·
modelo de reconhecimento de fala · CDN para servir o áudio no editor.

## 15. Mapa de acoplamento

- **Spelt entrega:** assinatura, planos, créditos, SSO, cobrança
- **Produto constrói:** motor de transcrição, editor, tabela de pesos, storage
- **Kit facilita:** gate de saldo, ganchos de provisionamento, API-keys, webhook
- **Kit absorve:** nada novo — o custo por operação já é resolvido no produto

## 16. Decisões em aberto

- Créditos avulsos devem ser consumidos antes ou depois da franquia?
- Tradução vira crédito separado ou continua como peso `2×`?
- Guardar o áudio original é obrigatório ou opção do cliente?

## 17. Compliance / operação

Conteúdo de terceiros: LGPD com base legal de execução de contrato, expurgo por
`retencao_dias`, e exclusão sob demanda em até 72 horas. Áudio cifrado em repouso.
