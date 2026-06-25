---
projeto: Nextgest
tipo: módulo
status: ciclo completo (4a–4c + 5a adesão + 5b webhook/reconciliação)
criado: 2026-06-25
tags: [nextgest, central, cobranca, assinatura, faturamento, saas]
---

# Cobrança da Assinatura SaaS

> Projeto: [[Nextgest - Visão Geral]] · Decisão: [[Decisões de Arquitetura]] (**D58**) ·
> Fundação do faturamento. Ver [[Cadastro Central do Estabelecimento]] e [[Painel Super-Admin (Central)]].

## ⚠️ Não confundir
Esta é a mensalidade do **estabelecimento → Nextgest** (o salão paga o SaaS). É **diferente** do
**Clube de Assinatura** ([[Clube de Assinatura (Fase A)]]), que é **cliente → salão** e vive no banco
do tenant. Aqui é tudo **central** (tabelas `assinaturas`/`faturas`).

## Fases
- **4a (D58):** modelo de dados + cálculo de situação + backfill. **Sem** UI, geração, gateway, bloqueio.
- **4b (D59 — feita):** tela de Faturamento no admin (configurar assinatura, gerar/marcar faturas, ver
  situação). Ainda **sem** gateway e **sem** bloqueio.
- **4c (D60 — feita):** suspensão/bloqueio efetivo no login do painel para `suspensa`/`cancelada` +
  banner de carência para `atrasada`. Estado distinto do "inativo" (ver
  [[Mapeamento Central x Tenant (auditoria pré-planos)]]).
- **5a (D61 — feita):** adesão recorrente via Mercado Pago (Preapproval, sandbox) — cria a recorrência
  e expõe o link de adesão. **Sem webhook ainda.**
- **5b (D62 — feita):** webhook do MP (valida assinatura, confirma cobranças → espelha `faturas`; falha
  → carência) + reconciliação agendada. **Ciclo de cobrança completo.**

## Webhook + reconciliação (5b — D62)
- **Rota pública** `POST /webhooks/pagamentos/{gateway}` → `WebhookPagamentoController` (central; CSRF
  dispensado em `webhooks/*`). Outros gateways = stub 200.
- **Segurança (nº 1):** `ValidadorWebhook` valida `x-signature` — manifest
  `id:{data.id};request-id:{x-request-id};ts:{ts};`, HMAC-SHA256 hex com `webhook_secret`, `hash_equals`.
  Inválida/sem segredo → **401, sem processar**.
- **Não confia no corpo:** consulta o recurso na API (`PreapprovalClient`) e espelha.
- **Idempotência:** `webhook_eventos` (unique `gateway`+`evento_id`; chave `authorized_payment:<id>` /
  `preapproval:<id>:<status>`) + `updateOrCreate` da fatura por competência.
- **Efeitos:** pagamento **approved** → fatura **paga** (`mercadopago`, `gateway_referencia`) + `ativa`;
  **rejected** → fatura **vencida na data da falha** → carência 20 dias (4c). `preapproval` →
  `mp_status`; `cancelled` → `cancelada`.
- **Respostas:** válido (mesmo duplicado) → 200; inválido → 401; falha de API → 500 (MP reenvia).
- **Reconciliação:** `nextgest:reconciliar-assinaturas` (agendado `dailyAt 03:10`) — reaplica via o
  mesmo processador (idempotente); rede de segurança caso um webhook não chegue.
- **Observabilidade (logs):** o handler loga `info`/`warning` nos pontos de decisão — recebido
  (tipo/`action`/`data.id`/`live_mode`), resultado da validação (válida/`inválida → 401`), duplicado,
  recurso não encontrado, e o desfecho (paga / vencida na falha / status atualizado). **Só fatos,
  nunca segredos/assinatura/corpo bruto.** Permite acompanhar no `laravel.log` (ex.: "Simular
  notificação" do painel MP → entrada → válida → recurso `123456` não encontrado → ignorado).

## Mercado Pago — mapeamento da API (Preapproval, confirmado no Passo 0 / D61)
- **Endpoint:** `POST https://api.mercadopago.com/preapproval`. Auth: `Bearer <access_token>` (TEST no
  sandbox). Consulta: `GET /preapproval/{id}`.
- **Fluxo "pago pendente"** (o que usamos — sem capturar cartão no nosso front): `status:"pending"` e
  **sem `card_token_id`** → resposta com **`init_point`** (página hospedada do MP; o dono loga e cadastra
  o cartão). Existe também o fluxo "pago autorizado" (exige `card_token_id` via Bricks) — **não usamos**.
- **Payload:** `reason`, `external_reference` (=tenant_id), `payer_email` (obrigatório), `back_url`,
  `status`, `auto_recurring { frequency:1, frequency_type:"months", transaction_amount, currency_id:"BRL",
  start_date, end_date?, free_trial? }`.
- **Gotcha:** `start_date` exige **milissegundos + Z** (ex.: `2026-06-25T18:35:00.000Z`). Carbon
  `toIso8601String()` (sem ms) → erro `Invalid format`. Usar `->utc()->format('Y-m-d\TH:i:s.v\Z')`.
- **Status:** `pending | authorized | paused | cancelled`. **Webhook (5b):** tópicos
  `subscription_preapproval` (estado da assinatura) e `subscription_authorized_payment` (cobranças).

## Adesão recorrente (5a — D61)
- **Config** `config/mercadopago.php` (token via `.env`, nunca cravado; `base_url`, `back_url`, timeout).
- **Client** `App\Services\MercadoPago\PreapprovalClient` (`criarPreapproval`/`consultar`); falha →
  `MercadoPagoException` (mensagem amigável; log só com `http_status`+`mp_message`, **sem token**).
- **Colunas** em `assinaturas`: `mp_preapproval_id` (unique), `mp_status`, `link_adesao` (init_point),
  `cobranca_automatica`.
- **UI** (tela Faturamento): botão **"Ativar cobrança automática"** — **idempotente** (não recria),
  exige `valor_mensal>0` e `dono_email`, trata erro sem 500, mostra o link de adesão + status do MP.
- **Validação:** testes com `Http::fake` (7); real no sandbox confirmou conectividade e corrigiu o
  formato de `start_date`. A criação real ainda dá **500 opaco** do MP (conta de teste sem Assinaturas
  habilitadas / test buyer) — passo manual no painel do MP. **`link_pagamento`/webhook = 5b.**

## Suspensão e carência (4c — D60)
- **Middleware `GarantirAssinaturaAtiva`** no grupo `painel` (guard `web`) — **só** o painel, nunca o
  portal/cliente nem o `/admin`. Roda **depois** da tenancy + `GarantirTenantAtivo`. Enforcement **ao
  vivo** (`situacaoAcesso()`, sem cron): `suspensa`/`cancelada` → redireciona para a tela de suspensão;
  `atrasada` (e demais) → segue. Tenant sem assinatura (null) não bloqueia. Auto-isento na tela de
  suspensão (sem loop) e no logout (deixa sair).
- **Tela de suspensão** `App\Livewire\Auth\AssinaturaSuspensa` (`painel.assinatura.suspensa`, layout
  `auth`, sem login): mostra a fatura pendente; "Pagar agora" só com `link_pagamento` (nulo hoje →
  orientação; pronta pro gateway). Distinta do `ativo=false` (404).
- **Banner de carência** no layout do painel (`atrasada`): "venceu em DD/MM, regularize em até N dias
  (até DD/MM)". Gate por **`can('ver_financeiro')`** (existente, exclusiva do Dono no seeder; ajustável).
- **Portal do cliente intacto**; **reversível ao vivo** (marcar fatura paga → próximo request volta a
  200).

## Tela "Faturamento" (4b — D59)
`App\Livewire\Admin\Faturamento` (rota `admin.tenant.faturamento` =
`/admin/estabelecimentos/{tenantId}/faturamento`, `auth:admin`). Botão **"Faturamento"** na lista +
atalho no Detalhe. **firstOrNew + save no 1º uso** (cria a assinatura com defaults se faltar).
- **Topo:** badge da situação (`situacaoAcesso()`) + "vencida há N dias / carência até DD/MM" —
  **informativo, não bloqueia** (login do tenant `suspenso` continua 200; bloqueio é a 4c).
- **Config:** valor mensal (editável), início, trial **ou** 1ª cobrança (sobrescreve), dia de
  vencimento (1–28), status e observações. **Status manual só `em_teste/ativa/cancelada`** —
  `atrasada/suspensa` são derivadas. Plano/recursos ficam na tela Editar.
- **Faturas:** gerar (competência/valor/vencimento; unique → erro amigável, sem 500), marcar paga
  (data + forma, default `manual`), reverter (paga→aberta), cancelar. `link_pagamento` nulo (gateway é
  a Fase 5). Dinheiro em **decimal**. **Sem trilha de auditoria** de quem marcou/reverteu (melhoria futura).

## Modelo de dados (central)
- **`assinaturas`** (1:1 com `tenants`): `tenant_id` (unique, FK), `plano` (snapshot), `valor_mensal`
  (snapshot, decimal), `data_inicio`, `trial_dias`, `data_primeira_cobranca`, `dia_vencimento` (1–28),
  `status` (`em_teste|ativa|atrasada|suspensa|cancelada`), `observacoes`.
- **`faturas`** (1:N): `assinatura_id`, `competencia` (1º dia do mês), `valor` (snapshot),
  `data_vencimento`, `status` (`aberta|paga|atrasada|cancelada`), `data_pagamento`, `forma_pagamento`
  (`manual|mercadopago|asaas`), `link_pagamento`, `gateway_referencia`. **Unique** (`assinatura_id`,
  `competencia`) — não duplica fatura do mês.
- Models `App\Models\{Assinatura,Fatura}` (trait `CentralConnection`); `Tenant::assinatura()` (hasOne).
- **Snapshots:** `valor_mensal`/`valor` preservam o preço do momento — mudar `config/planos.php` não
  reescreve faturas/assinaturas antigas.

## Regras de negócio (spec do Fabio — vale para a fase 4 inteira)
- **Vencimento:** `dia_vencimento` (dia fixo do mês, 1–28) OU dia da adesão OU `data_primeira_cobranca`.
- **Trial:** `trial_dias` (15/30/60/90…) OU `data_primeira_cobranca` (sobrescreve). 1ª cobrança =
  `data_primeira_cobranca ?? data_inicio + trial_dias`.
- **Carência:** **20 dias** após o vencimento (`config('cobranca.carencia_dias')`). Dentro da carência
  o acesso segue (situação "atrasada"); passados os 20 dias → "suspensa".

## Fonte única do estado: `Assinatura::situacaoAcesso()`
Consumida pela tela 4b e pela suspensão 4c (não duplicar a regra):
1. `cancelada` (manual) → `cancelada`.
2. hoje < 1ª cobrança → `em_teste`.
3. sem fatura **não paga** vencida → `ativa`.
4. fatura não paga mais antiga vencida há `1..carência` → `atrasada`.
5. vencida há `> carência` → `suspensa`.

"Vence hoje" ainda **não** é atraso (conta a partir do dia seguinte). Fronteira testada: **dia 20 =
atrasada, dia 21 = suspensa**. Faturas `paga`/`cancelada` não contam.

## Backfill
`php artisan nextgest:provisionar-assinaturas` — **dry-run** por padrão; `--apply` grava.
Cria 1 assinatura `em_teste` por tenant **sem** assinatura (plano = `planoAtual()`; valor = `preco_mes`
do catálogo quando conhecido, senão 0; `data_inicio` = `created_at`; `trial_dias` = padrão).
**Idempotente / não-destrutivo:** pula quem já tem, nunca atualiza/apaga. No dev, provisionou os 4
tenants (`em_teste`); 2ª execução criou 0.

## Testes
- `tests/Feature/Cobranca/ModeloCobrancaTest.php` (10): fronteiras de situação (em_teste/ativa/atrasada
  dia 10/dia 20/suspensa dia 21), carência da config, fatura paga não conta, cancelada, override de 1ª
  cobrança, snapshot de valor, backfill (dry-run/apply/idempotência).
- `tests/Feature/Admin/FaturamentoTest.php` (8): guard; cria no 1º uso; salva config; barra status
  derivado; gera + barra duplicada; marca paga→ativa→reverte→cancela; badge suspensa no admin; botão.
- `tests/Feature/Cobranca/SuspensaoTest.php` (10): matriz HTTP — ativa/atrasada não bloqueiam (banner
  só Dono); suspensa/cancelada redirecionam (login e painel); tela isenta sem loop; logout isento;
  portal 200; inativo 404; reversível ao pagar.
- `tests/Feature/Cobranca/PreapprovalTest.php` (7): API **mockada** — payload do fluxo pending (sem
  card_token_id), persistência, idempotência, erro tratado, guardas (valor 0 / sem e-mail).
- `tests/Feature/Cobranca/WebhookMercadoPagoTest.php` (10): rejeição 401 (assinatura inválida/sem
  segredo); aprovado→paga/ativa; idempotência (reenvio); recusado→vencida na falha→atrasada/suspensa;
  preapproval authorized/cancelled; desconhecido (ack); falha de API→500; reconciliação.
Suíte **542/542**. Validação ao vivo no dev: POST sem/forjada assinatura → 401.

## Limites / produção
Ciclo de cobrança completo no **dev/sandbox**. Para produção: trocar para **credenciais de produção**
do MP (token + webhook secret), cadastrar a **URL real** do webhook (`nextgest.com.br`, HTTPS público —
nada de túnel), rodar as migrations centrais com **backup antes**, e manter o **scheduler** rodando
(`php artisan schedule:run` no cron) para a reconciliação. Token/segredo **só via env**, nunca expostos.
Cuidado redobrado (mexe em dinheiro e no login de clientes reais). Portal/Clube/spatie/motor intactos.
