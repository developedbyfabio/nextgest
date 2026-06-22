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

## Resumo do dia (in-app, topo do painel)
Ao logar, o usuário vê um **resumo do dia** no topo — só **leitura** dos agendamentos de
HOJE (sem estado novo, sem push; push é Fase 1+). Componente **único** reusado no **dashboard
E na agenda** (cada papel aterrissa num dos dois: gestão no dashboard; profissional/recepção
na agenda — por isso fica nos dois lugares).

- **Componente:** `App\Livewire\Painel\ResumoDoDia` (fino) + `App\Services\Painel\ResumoDoDia`
  (cálculo, testável) + view `livewire/painel/resumo-do-dia`. Embutido com
  `<livewire:painel.resumo-do-dia />` no topo de `dashboard.blade.php` e de `agenda/index.blade.php`.
- **Conteúdo por papel/pessoa — gate por permissão/atributo, NUNCA por papel (D39):**
  - **Casa** (`can('ver_agenda')` → Dono/Gerente/Recepção): total de hoje + quantos **a
    confirmar** (status `pendente`). **Total = ocupantes** (exclui `cancelado`/`nao_compareceu`,
    igual ao `scopeOcupantes`/agenda).
  - **Pessoal** (atributo `e_profissional`): "Você tem **N** hoje" + o **próximo horário**
    (1 query ordenada, limit 1, com o cliente). Conta só os agendamentos **dele**.
  - **Os dois** (Dono que também atende): mostra os dois blocos compactos.
- **"Hoje"** = intervalo `[startOfDay, endOfDay]` local sobre `data_hora_inicio` (Carbon respeita
  `APP_TIMEZONE`) — mesmo critério da agenda.
- **Eficiência:** casa em **uma query agregada** (`COUNT` + `SUM(CASE WHEN status = 'pendente')`);
  pessoal = `count` + 1 query do próximo. **Contagem de query CONSTANTE** (não cresce com o
  volume) — `tests/Feature/Performance/ContagemQueriesTest.php` (`≤ 5`, igual com 3 ou 30
  agendamentos). **Vazio é estado válido** ("Nenhum agendamento para hoje" / "Nenhum agendamento
  seu hoje"), nunca erro.
- **Testes de papel:** `tests/Feature/Painel/ResumoDoDiaTest.php` (profissional vê pessoal;
  gestão vê casa; Dono-que-atende vê os dois; estados vazios).

## Relacionado
- [[Decisões de Arquitetura]] (D31)
- [[Vendas e Comanda]] (fonte do faturamento real) · [[Produtos e Estoque]]
- [[Modelo de Dados - Núcleo de Agendamento]] (agendamentos / `agendamento_servico`)
- [[Papéis e Permissões (RBAC)]] (gate por `ver_agenda` + atributo `e_profissional`)
