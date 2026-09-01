# Ideias do SaaS Kit

Bucket **provisório** de briefs de produtos que serão construídos sobre o [Starter Kit](../technical/starter-kit.md). Servem a dois propósitos:

1. **Exercício de acoplamento** — rodar cada produto pelas costuras do kit (entitlements, provisionamento, gate, SSO, webhooks) para descobrir limitações **no papel**, antes de escrever código.
2. **Base de partida** — quando o produto for realmente construído, o brief já está pronto; não é preciso redescrever tudo.

> ⚠️ **Não é código nem parte do Spelt.** É documentação de staging. O destino final destes docs ainda será decidido (provavelmente migram para o repositório do próprio produto quando ele nascer).

Cada brief termina com um **Mapa de acoplamento com o Kit** (o que o Spelt entrega · o que o produto constrói · o que o kit precisa facilitar · o que o kit deveria absorver), que é o insumo para evoluir a spec do kit.

## Modelo

Comece por [`TEMPLATE.md`](TEMPLATE.md) — brief guiado com o que precisa ser definido para cada produto novo, exemplos inline dos casos já feitos, e a **rubrica de "escopo rico"** usada na revisão.

## Briefs

| Produto | Perfil | Status |
|---|---|---|
| [Fábrica de Lead](fabrica-de-lead.md) | Provisionamento leve · entitlements ricos (créditos + capacidade) · recorrente | exercício concluído |
| [MailValidation](mail-validation.md) | Pay-as-you-go consumível · créditos com custo por tier · API própria | exercício concluído |
| [LinkTO](linkt-to.md) | Micro-site builder · só capacidade · domínio/SSL via Cloudflare | rascunho (teste do template) |
| [Psst](try-psst.md) | Gerador de "ganchos" (IA) multicanal · créditos + capacidade · trial · engine própria | **migrado → `trypsst.com`** (cópia canônica em `api.trypsst.com/docs/product/psst.md`; build em andamento) |
