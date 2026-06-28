---
projeto: Nextgest
tipo: módulo
status: G1 (conexão OAuth) — dev; "conectar de verdade" pendente das credenciais
criado: 2026-06-28
tags: [nextgest, pagamento, gateway, mercadopago, oauth, tenant]
---

# Gateway de pagamento do tenant (Modelo A)

> **Modelo A (direto pro dono):** o salão pluga a **própria** conta Mercado Pago; o dinheiro cai
> **nele**, o Nextgest orquestra mas **não toca no dinheiro** (sem split/marketplace). Decisão:
> [[Decisões de Arquitetura]] (**D78**). **Separado** da [[Cobrança da Assinatura SaaS]] (admin,
> Preapproval, dinheiro salão→Nextgest). Só **dev**.

## G1 — conectar a conta (OAuth). NÃO cobra ainda (G2).
- **Item de menu próprio "Gateway de pagamento"** (Gestão), rota `painel.pagamentos`, gated
  `@recurso('gateway')` + `can('gerenciar_pagamentos')` (Dono). O **hub de Integrações foi aposentado**
  (o editor manual de "colar token" saiu; MP do tenant agora é **só** OAuth).
- **Credenciais separadas:** `client_id`/`client_secret`/`redirect_uri` do app Nextgest **só no `.env`**
  (`config/pagamentos.php`) — nunca no banco/log. O **token do salão** fica **cifrado** no cofre
  `gateways_pagamento.credenciais` (`encrypted:array`, `hidden`); colunas públicas p/ exibir:
  `conta_externa_id`, `conta_externa_nome`, `conectado_em`.
- **Fluxo OAuth (seguro):** "Conectar" gera `nonce` na sessão + `state = base64(tenant|nonce)` →
  redireciona ao MP. **Callback central** `GET /oauth/mercadopago/callback` (slug `oauth` reservado;
  grupo `web` c/ sessão) **valida o `state` contra a sessão** (anti-CSRF — rejeita ausente/divergente),
  troca o `code` pelo token, grava no cofre do tenant e volta à tela. Token nunca logado/exibido.
- **Peças:** `Services\Pagamentos\MercadoPagoOAuth` (URL de autorização + troca de code + `/users/me`),
  `ConexaoGatewayMercadoPago` (orquestra state/sessão/gravação/desconectar), controller
  `Pagamentos\MercadoPagoOAuthController` (callback), tela `Painel\Pagamentos\Gateway`.

## Como operar (quando houver credenciais)
1. **Passo manual do Fabio (fora do código):** registrar o **Nextgest como app no Mercado Pago** com
   **OAuth habilitado**; definir o **Redirect URI** = `…/oauth/mercadopago/callback`; pegar
   `client_id`/`client_secret` → pôr no `.env` (`MP_OAUTH_CLIENT_ID`/`MP_OAUTH_CLIENT_SECRET`/
   `MP_OAUTH_REDIRECT_URI`).
2. Aí o dono abre **Gestão → Gateway de pagamento → Conectar Mercado Pago**, autoriza no MP e volta
   conectado.

> **PENDENTE (esperado):** sem as credenciais reais, "Conectar" avisa que a integração está pendente
> (não inventa credenciais). O fluxo todo é testado com **mock** (`GatewayOAuthTest`, 9).

## Testes
`tests/Feature/Pagamentos/GatewayOAuthTest.php` (MP mockado): state anti-CSRF; troca + token cifrado no
cofre; desconectar; callback HTTP conecta/rejeita; pendência de credenciais; gating 404. Suíte verde.

## Próximas fatias (roadmap do gateway)
- **G2:** cobrar a assinatura do Clube na conta do salão (adapter de cobrança hoje stub; Clube hoje
  manual — auditoria-primeiro).
- **G3:** webhook do pagamento → marca assinante pago/inadimplente (endpoint público, segurança própria
  como o webhook do SaaS).
