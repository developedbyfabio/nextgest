# Deploy de Produção (Bloco 1) — código + migrations D74–D90

> **Data:** 2026-07-01. **Ambiente:** produção (VPS Hostinger, `nextgest.com.br`,
> `187.127.24.165`). **Decisão do Fabio:** ambiente de teste (sem cliente real) →
> **backup dispensado** neste deploy. Publicado seguindo o "Roteiro de Deploy Seguro".

## O que subiu (D74 → D90, 21 commits)
- **Clientes (CRM)** — aba "Clientes" em Gestão: lista (nome/e-mail/telefone/última
  visita/selo Clube), busca, filtros (última visita, Clube), detalhe com últimos
  agendamentos, editar cliente, WhatsApp avulso, cards de resumo (total,
  inatividade clicável, Clube). *(D87, D88, D89)*
- **Favicon por tenant** na Aparência — upload processado + `<link rel=icon>` no head
  do portal e do painel. *(fatia favicon / D90)*
- **Salvar-por-card** na aba Automações do WhatsApp. *(D85)*
- **Consentimento de marketing** separado do opt-out geral (tela Opt-out +
  `Cliente::aceitaMarketing()`). *(D86)*
- **Módulo WhatsApp (código):** conexão, automações (lembrete/avaliação/cobrança/
  broadcast-cards), aquecimento, janela de horário, histórico, opt-out, aceite de
  risco, detecção de queda, melhorias de UI. *(D74–D84)* — **código no ar; a conexão
  só funciona após o Bloco 2 (Evolution em produção).**

## Migrations aplicadas (todas ADITIVAS — verificado antes: drops só em `down()`)
- **Central (1):** `2026_06_28_000010_create_jobs_table`.
- **Tenant (10):** `add_evolution_to_whatsapp_config`, `add_automacoes_to_whatsapp_config`,
  `add_oauth_to_gateways_pagamento`, `lembretes_servico_whatsapp`,
  `add_termo_to_whatsapp_config`, `pedidos_avaliacao_whatsapp`,
  `add_aquecimento_to_whatsapp_config`, `create_mensagens_whatsapp_e_janela`,
  `add_numero_teste_to_whatsapp_config`, `add_marketing_optout_to_clientes`.
- Rodadas com `migrate --force` (central) + `tenants:migrate --force` (tenant `teste`,
  único tenant). Nenhuma destrutiva; sem `fresh`/`reset`/`wipe`.

## Passos executados
`git pull --no-rebase` (ff, sem force) → `composer install --no-dev -o` (lock inalterado;
dump do autoloader p/ classes novas) → `npm run build` (foreground) → `migrate:status`
→ `migrate --force` → `tenants:migrate --force` → `config/route/view:cache` →
`queue:restart`.

## Validação (regressão, SEM tocar WhatsApp)
- Landing, portal do tenant e painel → **HTTP 200**.
- Rotas novas registradas: `{tenant}/painel/clientes`, `{tenant}/painel/aparencia`.
- Favicon: `<link rel="icon">` no head do portal (fallback padrão; upload próprio
  reflete quando o tenant subir um).
- Assets novos servidos (manifest/app.js 200) — build pegou.
- **Scheduler WhatsApp roda gracioso sem Evolution:** `nextgest:enviar-lembretes` e
  `nextgest:enviar-avaliacoes` → `exit 0`, "enfileirados: 0". Curto-circuitam quando
  o tenant não tem recurso, automação off ou `instancia` em branco, e capturam
  `WhatsAppException`/`ConnectionException`. **Não foi preciso pausar o scheduler.**
- Log sem erros (só ruído conhecido de bots).

## Pendente → Bloco 2
- WhatsApp aguarda a **Evolution API em produção** (Docker + Postgres + Redis, exposição
  mínima segura via Nginx/HTTPS + firewall). Só então conexão/automações funcionam.
- Nota de infra p/ Bloco 2: Redis **já existe** em produção (`QUEUE=redis`); a Evolution
  deve subir com Redis/Postgres **dedicados** (não reusar o do app). Docker **ainda não
  instalado** na VPS. Recursos folgados (6.3 GB RAM livre, 89 GB disco, 2 vCPU).
