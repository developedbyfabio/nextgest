---
projeto: Nextgest
tipo: módulo
status: em construção (4a — modelo/situação/backfill)
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
- **4a (esta — D58):** modelo de dados + cálculo de situação + backfill. **Sem** UI de operação, **sem**
  geração de faturas, **sem** gateway, **sem** bloqueio de login.
- **4b (futura):** tela de Faturamento no admin (gerar/marcar faturas, ver situação, link de pagamento).
- **4c (futura):** suspensão/bloqueio efetivo no login do tenant para assinatura `suspensa`/`cancelada`
  (estado distinto do "inativo" administrativo — ver [[Mapeamento Central x Tenant (auditoria pré-planos)]]).

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
`tests/Feature/Cobranca/ModeloCobrancaTest.php` (10): fronteiras de situação (em_teste/ativa/atrasada
dia 10/dia 20/suspensa dia 21), carência lida da config, fatura paga não conta, cancelada, override de
1ª cobrança, snapshot de valor, e backfill (dry-run/apply/idempotência). Suíte **507/507**.

## Limites desta fase
Sem UI de operação, sem geração de faturas, sem gateway, sem bloqueio de login; nada de
painel/portal/Clube/spatie/motor tocado. **Dev apenas — sem deploy.** Em produção, migrations centrais
com **backup antes** e backfill provisiona o tenant real.
