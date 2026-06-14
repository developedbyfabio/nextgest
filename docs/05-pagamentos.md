# 05 — Pagamentos

Migration: `...190003_*` (`gateways_pagamento`) e `...190004_*` (`pagamentos`,
`cartoes_tokenizados`, `webhooks_pagamento`).

## Segurança (D09/D21) — leia antes de tudo

- **Nunca** armazenar número de cartão, CVV ou validade. Só o **token** do gateway.
- **Credenciais do gateway** criptografadas: `App\Models\GatewayPagamento` usa
  cast `encrypted:array` em `credenciais` (e `$hidden`). Nunca em log/nota.
- Confiar no **webhook** do gateway para confirmar pagamento (Pix/boleto são
  assíncronos).

## Arquitetura plugável (adapter — D19)

- Interface `App\Services\Pagamentos\GatewayPagamento`:
  `cobrar`, `estornar`, `criarAssinaturaRecorrente`, `tratarWebhook`.
- `MercadoPagoGateway` implementa (hoje **stub**; integração real depois).
- `GatewayResolver::para($config)` instancia o adapter pelo `provedor`;
  `padraoDoTenant()` pega o gateway `ativo`+`padrao` do tenant.
- Convenção do construtor de adapter: `(array $credenciais, string $modo)`.

## Tabelas

- **gateways_pagamento** — `provedor`, `apelido`, `credenciais` (encrypted),
  `modo` (sandbox/producao), `ativo`, `padrao`.
- **pagamentos** — liga a `venda_id` e/ou `assinatura_id`; `metodo` (pix,
  cartao_credito, cartao_debito, dinheiro, maquininha), `status` (pendente,
  aprovado, recusado, estornado, cancelado), `gateway_transacao_id`,
  `pix_copia_cola`, `link_pagamento`, `pago_em`.
- **cartoes_tokenizados** — só token + dados de exibição (bandeira, ultimos4).
- **webhooks_pagamento** — auditoria do payload recebido (`processado`).

## Regras

- **Presencial**: `gateway_id` nulo, `status=aprovado` na hora, sem chamar gateway.
- **Online**: cria cobrança no gateway, fica `pendente` até o webhook confirmar.
- **Recorrência do clube**: `assinatura` recorrente no gateway; cada ciclo gera
  um `pagamento` ligado à `assinatura_id`.
- **Webhook central**: `POST /webhooks/pagamentos/{gateway}` (rota central, sem
  tenant na URL); o tenant é resolvido pelo conteúdo/assinatura do webhook.

## Em aberto (A confirmar)

- Recorrência Mercado Pago: preapproval nativo vs cobrança mensal por job.
- Estorno parcial / regras de reembolso.
- Pagamento dividido (vários pagamentos por venda) no MVP — modelo já suporta.
- Credenciais reais do Mercado Pago (sandbox e produção).
