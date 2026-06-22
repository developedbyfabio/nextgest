---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-22
tags: [nextgest, indicadores, ui, painel, retencao, frequencia, rbac]
---

# Indicadores — aba (Fase II)

> Projeto: [[Nextgest - Visão Geral]] · Motor: [[Indicadores — motor (Fase I)]] ·
> Ver [[Dashboard do Dono]], [[Papéis e Permissões (RBAC)]] (D39).

## O que é
A aba **"Indicadores"** no painel — **casca fina** sobre o motor `App\Services\Painel\IndicadoresClientes`
(Fase I). É **apresentação**: NÃO recalcula nada (sem SQL agregado/loop no componente); todo número
vem do serviço. Componente `App\Livewire\Painel\Indicadores` + view `livewire/painel/indicadores`.

## Permissão (D39 — gate por permissão, nunca papel)
Nova permissão **`ver_indicadores`** (Dono **+ Gerente**), adicionada ao `TenantDatabaseSeeder`
(entra na lista geral → Dono tem tudo; Gerente herda por `array_diff`; Recepção/Profissional têm
allow-list própria → não têm). Re-sync nos tenants com **`php artisan tenants:seed`** (idempotente).
Rota `painel.indicadores` sob `auth:web` + **`can:ver_indicadores`** (sem `recurso:` — não é
módulo de flag). Item de menu no grupo **Gestão** (`@can('ver_indicadores')`). Sem a permissão →
**não vê o item** e a rota dá **403 cru**.

## Os 4 cards
- **Risco (sumindo):** total (`emRisco()->total()`) + destaque ("mais atrasado: N dias"). Clique →
  drill-in com a lista paginada.
- **Frequência:** contagem por bucket (Vai sempre / Regular / Esporádico / Novos-poucos dados) de
  `frequencia()`. Clique no bucket → drill-in `clientesPorBucket($bucket)`.
- **Ticket médio:** `ticketMedio($inicio, $fim, $profissionalId)` — respeita período + profissional.
- **Retenção:** `retencao($inicio, $fim)` — % do período vs. anterior (janela do filtro).

## Filtros (aplicados SÓ onde o motor aceita)
Decisão (não tocar o motor da Fase I): **período** (presets Hoje/7/30/90 dias/Mês/Personalizado) →
**ticket médio + retenção**; **profissional** → **ticket médio**. **Risco e frequência** são
"estado atual do hábito" (todo o histórico pago do cliente) — **não** recebem filtro. **Sem filtro
de unidade** (o motor não suporta; ficaria para uma extensão futura do motor). Há um aviso curto na
tela explicando que risco/frequência não dependem do período.

## Drill-in (seção expandível, uma por vez)
Clicar num card abre uma seção com a **lista paginada** do segmento, usando a **paginação do
próprio serviço** (`->links()`), nunca materializando tudo:
- **Risco:** nome, visitas, intervalo médio, **dias sem vir** — ordenado pelo mais atrasado.
- **Bucket:** nome, visitas, intervalo médio.
- **Nome do cliente:** o serviço retorna `cliente_id` (não o nome). A aba resolve os nomes **só da
  página atual** com **1 query** (`Cliente::whereIn(...)->pluck('nome','id')`) — é exibição, não
  recálculo, e mantém a contagem constante.
- **Sem link para "ficha do cliente":** essa tela **não existe** no painel hoje. Criar a ficha é um
  módulo à parte (fora do escopo da Fase II). **Gancho futuro:** quando existir, o nome vira link;
  e a lista de risco é o ponto de partida do **disparo de WhatsApp** (Fase 4, não aqui).

## Eficiência (herda o motor; sem N+1)
- `tests/Feature/Performance/ContagemQueriesTest.php`: render da aba (cards + drill-in de risco) com
  5 e depois 45 clientes (15→135 visitas) → **contagem de query IGUAL** (`n45 === n5`), ≤ 20.
  A aba não reintroduz N+1.

## Testes
- `tests/Feature/Painel/IndicadoresAbaTest.php`: permissão (Dono/Gerente 200; Recepção/Profissional
  **403 cru**); os 4 cards exibem os **mesmos números do serviço** (ticket conferido contra
  `IndicadoresClientes`); drill-in de risco abre a lista com o nome, ordenada; drill-in por bucket;
  **filtros** mudam o ticket (7d vs 90d → 50 vs 75); **estado vazio** não quebra.
- Suíte: **387 → 394** verde.

## Verificação
- Rota como guest → **302** login (sem 500). `volumeteste` reativado p/ dados ricos. `laravel.log`
  **vazio** (a aba/serviço não logam). Ticket médio bate com o critério do dashboard (paga + data).

## Reuso (fonte de verdade única)
Cálculo: **só** o motor da Fase I. Visual: cards `x-ng.indicador`/`.ng-surface`, filtros e estado de
erro recuperável **do dashboard**; `x-ng.empty` no vazio; paginação Flux (`->links()`). Nada de
componente visual novo.
