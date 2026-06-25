---
projeto: Nextgest
tipo: módulo
status: em construção (4a modelo + 4b tela Faturamento)
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
- **4c (futura):** suspensão/bloqueio efetivo no login do tenant para assinatura `suspensa`/`cancelada`
  (estado distinto do "inativo" administrativo — ver [[Mapeamento Central x Tenant (auditoria pré-planos)]]).
- **5 (futura):** gateway (link de pagamento / webhook).

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
  derivado; gera + barra duplicada; marca paga→ativa→reverte→cancela; suspensa informativo **sem**
  bloquear login; botão na lista.
Suíte **515/515**.

## Limites (até aqui)
**Sem gateway** (`link_pagamento` nulo) e **sem bloqueio de login** (situação só informativa) — isso é
5/4c. Nada de painel do dono/portal/Clube/spatie/motor tocado. **Dev apenas — sem deploy.** Em produção,
migrations centrais com **backup antes**; o tenant real é configurado pela tela Faturamento.
