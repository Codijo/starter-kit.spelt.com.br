# Briefs de produto

Antes de escrever código sobre o kit, escreva o **brief**. Ele força as decisões
que o kit precisa saber — como o produto cobra, o que limita, o que provisiona —
e revela no papel as limitações que apareceriam só depois, no código.

## Como usar

1. Copie o [`TEMPLATE.md`](TEMPLATE.md) para o `docs/` do seu projeto.
2. Responda cada `▢`. Enquanto houver campo vago, o escopo não está fechado.
3. Termine pelo **Mapa de acoplamento** (§15): o que o Spelt entrega, o que o
   produto constrói, o que o kit facilita.

## Exemplos preenchidos

Um template vazio não ajuda na hora de preencher o seu. Estes dois são
**fictícios** e foram escritos para contrastar os dois caminhos que o kit
suporta:

| Exemplo | Cobrança | Quota | O que exercita |
|---|---|---|---|
| [Transcreve](exemplo-transcreve.md) | misto | **créditos consumíveis** | custo por operação, API própria, webhook de saída |
| [Cardápio](exemplo-cardapio.md) | recorrente | **só capacidade** | caminho sem crédito, renderização pública, domínio do cliente |

Se o seu produto cobra por uso, comece pelo Transcreve. Se cobra por tamanho da
operação, comece pelo Cardápio.

> Nenhum dos dois existe. São exemplos de **como responder**, não de o que construir.
