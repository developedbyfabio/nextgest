---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-21
tags: [nextgest, dashboard, metricas, charts, tenant]
---

# Dashboard do Dono

> Projeto: [[Nextgest - Visão Geral]] · Decisão: [[Decisões de Arquitetura]] (D31) ·
> Etapa 4 da evolução visual.

## O que é
Painel do tenant com indicadores e gráficos sobre **dados reais**, para Dono/Gerente.

- Componente: `App\Livewire\Painel\Dashboard` (rota `painel.dashboard`).
- Agregações: `App\Services\Dashboard\Metricas`.
- Permissão: **`ver_dashboard`**. Quem não a tem (Profissional/Recepção) é redirecionado
  à agenda (`ver_agenda`/`ver_agenda_propria`).

## Filtros
- **Período:** `hoje`, `7d`, `30d` (padrão), `mes`, `custom` (data início/fim).
- **Unidade:** filtro aparece só quando há **2+ unidades** ativas.
- Mudar filtro atualiza os gráficos **ao vivo** (canvas em `wire:ignore`), sem recarregar.

## Indicadores e gráficos
- Série de agendamentos no período; **taxa de comparecimento**
  (`concluido` / `nao_compareceu` / `cancelado`); clientes novos; top
  serviços/profissionais; distribuição por hora.
- **Gráficos com Chart.js** (via Vite), usando as cores do tema do estabelecimento.

> [!warning] Faturamento é ESTIMADO
> Não há módulo de Vendas/Clube ainda. O faturamento é **estimado** a partir dos
> snapshots de preço em `agendamento_servico` dos agendamentos com status
> `concluido` no período. Quando a Fatia 2 (vendas) existir, a fonte deve migrar para
> as vendas reais.

## Portabilidade das consultas
As agregações usam apenas `count`/`sum`/`groupBy` por coluna (iguais em SQLite de
teste e MySQL de produção). Extrações de data/hora (série temporal, distribuição por
hora) são feitas **em PHP** a partir de uma consulta enxuta — sem funções SQL
específicas de banco. Importa para os testes (SQLite) baterem com produção (MySQL).

## Dados de demonstração
O `nextgest:demo` enriquece o tenant com ~90 dias de histórico determinístico
(semente fixa, marcado `[demo]`, idempotente) para os gráficos terem forma.

## Elevação visual (Etapa B, 2026-06-21)
Dashboard levado ao nível "de ponta", dark-safe:
- **KPIs** (`x-ng.indicador`) e **gráficos** (`x-ng.grafico`) reconstruídos sobre
  `.ng-surface` (superfície da marca) — ícone em chip, número maior, hover sutil.
- **Chart.js** com eixos/legendas/**tooltips** nas cores da marca (lidas das CSS vars
  em runtime via `ngVar()`), grade sutil legível no claro e no escuro, doughnut com
  `cutout`. Mantém `wire:ignore` (Livewire não recria o canvas; atualiza via evento
  `metricas-atualizadas`).
- **Estados:** skeleton (`.ng-skeleton-portal`) nos KPIs durante o recálculo, dim nos
  gráficos, **empty** temático por bloco e **estado de ERRO recuperável**
  (`try/catch` em `Dashboard::dados()` → `erro=true` + botão "Tentar de novo").
- **Filtros** período/unidade com spinner discreto; responsivo 2→3→5 colunas.
- Ver [[Identidade Visual do Estabelecimento (Tema)]] e
  [[Auditoria de UI (Portal e Painel)]].

## Relacionado
- [[Decisões de Arquitetura]] (D31)
- [[Modelo de Dados - Núcleo de Agendamento]] (agendamentos / `agendamento_servico`)
