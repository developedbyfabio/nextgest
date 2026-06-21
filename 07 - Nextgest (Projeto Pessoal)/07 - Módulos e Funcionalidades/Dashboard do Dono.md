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
Dois grupos de KPIs (reusam `x-ng.indicador`):
- **Financeiro (fonte: vendas pagas — Fatia 2D):** **Faturamento** (real, com variação
  vs. período anterior), **Vendas pagas**, **Ticket médio** (faturamento ÷ nº vendas),
  **Comissão a pagar** (soma de `venda_itens.valor_comissao` das vendas pagas).
- **Operação (fonte: agendamentos):** Agendamentos (com tendência), Comparecimento,
  Clientes novos, Clientes recorrentes.
- **Gráficos** (Chart.js, cores do tema, reusam `x-ng.grafico`): **Faturamento por dia**
  e **Mais vendidos (R$)** (vendas pagas); Agendamentos por dia; Taxa de comparecimento;
  Serviços mais agendados; Horários mais movimentados.

> [!check] Faturamento agora é REAL (Fatia 2D, 2026-06-21)
> Faturamento = soma de `vendas.valor_total` das vendas com `status = paga` no
> período/unidade. **Atendimento sem comanda NÃO vira faturamento** (só conta o que foi
> efetivamente cobrado) — o correto. Substitui o "ESTIMADO" antigo (que somava
> `agendamento_servico` de concluídos). `Metricas` lê de `vendas`/`venda_itens`. Empty-
> state quando não há vendas no período (sem inventar número). Ver [[Vendas e Comanda]].

## Portabilidade das consultas
As agregações usam apenas `count`/`sum`/`groupBy` por coluna (iguais em SQLite de
teste e MySQL de produção). Extrações de data/hora (série temporal, distribuição por
hora) são feitas **em PHP** a partir de uma consulta enxuta — sem funções SQL
específicas de banco. Importa para os testes (SQLite) baterem com produção (MySQL).

## Dados de demonstração
O `nextgest:demo` enriquece o tenant com ~90 dias de histórico determinístico
(semente fixa, idempotente): agendamentos e, na **2D**, **vendas pagas** ao longo do
tempo — ~70% dos atendimentos concluídos viram comanda paga (serviços), parte com um
produto de balcão (se houver estoque), com a `data` da venda retroagida ao atendimento.
Coerente: usa `Comanda`/`MovimentadorEstoque` (baixa de estoque e comissão batem).

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
- [[Vendas e Comanda]] (fonte do faturamento real) · [[Produtos e Estoque]]
- [[Modelo de Dados - Núcleo de Agendamento]] (agendamentos / `agendamento_servico`)
