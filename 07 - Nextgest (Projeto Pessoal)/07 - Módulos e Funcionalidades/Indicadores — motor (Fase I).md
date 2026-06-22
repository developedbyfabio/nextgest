---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-22
tags: [nextgest, indicadores, retencao, frequencia, metricas, performance, tenant]
---

# Indicadores — motor (Fase I)

> Projeto: [[Nextgest - Visão Geral]] · Ver [[Performance]], [[Dashboard do Dono]],
> [[Vendas e Comanda]]. **Fase I = só o motor de números (sem UI).** A aba/tela é a Fase II
> ([[Indicadores — aba (Fase II)]]); os números (1,5 e 3) viram ajuste do Dono na Fase III.

## O que é
`App\Services\Painel\IndicadoresClientes` — motor de **retenção/frequência** dos clientes, só
**leitura** no contexto do tenant. Toda métrica é **set-based** (GROUP BY / subconsulta);
**nunca** loop por cliente (proibido N+1, provado por teste de contagem).

## Definições fixas (decisões do Fabio)
- **"Visita" = comanda PAGA** — `vendas.status = 'paga'`; **data da visita = `vendas.data`**
  (mesmo critério "paga + data" que `App\Services\Dashboard\Metricas` usa no faturamento).
  Vínculo `vendas.cliente_id` (comandas de balcão sem cliente ficam de fora). Comandas sem
  nenhuma paga não entram em nenhuma métrica de hábito.
- **Intervalo médio** = média dos dias entre visitas pagas **consecutivas**. Como a média dos
  gaps telescopa para **(última − primeira) ÷ (visitas − 1)**, basta `MIN/MAX/COUNT` —
  **sem janela/LAG** (portável MySQL + SQLite).
- **Risco ("sumindo")** = ≥ **3** visitas **E** *dias desde a última* > *intervalo médio × **1,5***.
- **< 3 visitas** → bucket **"novos/poucos dados"** (NÃO conta como risco).

## Constantes nomeadas (único lugar — Fase III configurável)
Em `IndicadoresClientes`: `MIN_VISITAS_HABITO = 3`, `MULTIPLICADOR_RISCO = 1.5`,
`FREQ_SEMPRE_DIAS = 14`, `FREQ_REGULAR_DIAS = 35`. Nenhum literal espalhado.

## As 4 métricas (fórmula)
- **`emRisco($porPagina)`** → paginador. Clientes com `visitas ≥ 3` e
  `dias_desde_ultima > intervalo_medio × 1,5`, ordenados pelo mais atrasado
  (`dias_desde_ultima − intervalo_medio` desc). 1 query agregada (+1 do count da paginação).
- **`frequencia()`** → `{sempre, regular, esporadico, novos}` (contagens). Bucketização por
  intervalo médio: **≤14** = sempre (semanal), **>14 e ≤35** = regular (mensal), **>35** =
  esporádico; **<3 visitas** = novos. Uma query (subconsulta + `SUM(CASE)`).
  `clientesPorBucket($bucket, $porPagina)` lista quem está em cada bucket.
- **`ticketMedio($inicio?, $fim?, $profissionalId?)`** → média de `valor_total` das pagas, com
  recortes opcionais por período e profissional. Uma query (`AVG`). 0 sem comandas.
- **`retencao($inicio, $fim)`** → `{base, voltaram, taxa, ...}`. **Fórmula:** % dos clientes que
  tiveram comanda paga no período **anterior** (mesma duração, imediatamente antes) e voltaram a
  ter no período **atual** = `|anterior ∩ atual| ÷ |anterior|`. Uma query (join de dois
  conjuntos `DISTINCT`). Coorte completa fica para depois.

## Portabilidade da diferença de dias
`diffDiasSql()` escolhe a expressão pelo driver: **`DATEDIFF`** (MySQL, produção) /
**`julianday`** (SQLite, testes). Por isso o filtro/ordenação de risco moram no SQL (paginável).
Nos testes, as visitas são criadas à meia-noite → diffs em dias inteiros.

## Costura para agregados pré-calculados (futuro, pós-VPS)
Toda métrica de hábito parte de **`agregadoBase()`** — a fonte única do agregado por cliente
(`cliente_id, visitas, primeira, ultima, intervalo_medio`). Para ler de uma **tabela
pré-calculada** (cálculo noturno), basta sobrescrever `agregadoBase()`; a interface pública não
muda. O cálculo noturno em si **não** foi implementado nesta fase.

## Índice (Fase I — aditivo)
Migração `…_add_indice_cliente_status_data_to_vendas`: índice composto
**`vendas (cliente_id, status, data)`** (`vendas_cliente_status_data_index`). Já existia o índice
**simples** de FK em `cliente_id`, mas a agregação filtra `status='paga'` e usa `data`. **EXPLAIN
(volumeteste)** da agregação por cliente:
- **Antes:** `type=index key=vendas_cliente_id_foreign rows=7946 [Using where]` (full index scan +
  table lookup p/ status/data).
- **Depois:** `type=range key=vendas_cliente_status_data_index rows=3973 [Using where; Using index]`
  (**covering** — sem lookup por linha). Retenção usa `vendas_data_index` (range em `data`).
Só `CREATE INDEX` (aditivo, reversível). `php artisan tenants:migrate`.

## Testes
- **Contagem CONSTANTE** (`tests/Feature/Performance/ContagemQueriesTest.php`): mede cada métrica
  com 5 e depois 45 clientes (15→135 visitas) e exige **contagem igual** (`n45 === n5`) e pequena
  (risco ≤2, frequência/ticket/retenção ≤1, bucket ≤2). Vermelho = N+1 → corrigir o SQL.
- **Correção** (`tests/Feature/Painel/IndicadoresClientesTest.php`): sumido claro entra no risco;
  regular em dia fica fora; <3 visitas vai p/ "novos" (nunca risco); buckets de frequência;
  ticket médio com recortes; retenção montada à mão (base 2, voltaram 1, 50%); cliente sem paga
  não entra em nada.
- **Verificação:** rodado no `volumeteste` (8000 vendas/2711 pagas/3000 clientes) — contagens
  constantes (risco 2, frequência 1, ticket 1, bucket 2, retenção 1); `laravel.log` **vazio**
  (o serviço não loga).

## Reuso e fora de escopo
- Reusa o critério **"paga + data"** do `Metricas` (dashboard) e o precedente de agregação por
  cliente (`Metricas::clientesRecorrentes`). O **Kanban CRM** não tinha noção de ativo/inativo —
  nada a reusar lá.
- **SEM UI** (Fase II), sem cálculo noturno (futuro), sem tocar segurança/RBAC/`MotorDisponibilidade`/
  comanda (além de ler). Migração só aditiva.
