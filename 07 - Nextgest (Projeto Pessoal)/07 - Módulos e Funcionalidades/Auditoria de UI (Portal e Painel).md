---
projeto: Nextgest
tipo: auditoria
status: vivo
criado: 2026-06-21
tags: [nextgest, ui, auditoria, portal, painel]
---

# Auditoria de UI — Portal do Cliente e Painel do Dono

> Projeto: [[Nextgest - Visão Geral]] · Padrão: [[Padrao de UI-UX (Design System)]]
> Feita em 2026-06-21 com app servida (HTTP real) + render dos componentes
> Livewire + leitura do código. Honesta: classifica o que existe de fato.

## Método
- App servida em porta alta (`--host=0.0.0.0`); páginas públicas verificadas por
  HTTP real; telas logadas renderizadas via `Livewire::test()->html()` (inclusive
  com tema escuro custom para expor inconsistências); leitura das views/componentes.
- Legenda: ✅ existe-e-polido · 🎨 existe-mas-feio/inconsistente · 🐛 existe-mas-quebrado
  · ✨ faltando.

## Portal do cliente
- ✅ **Home deslogada** (`/{slug}`) — identidade do tenant, "como funciona", CTAs.
- ✅ **Login / Registrar** — layout split com a marca do tenant (a doc antiga dizia
  que usava só a marca Nextgest; **na verdade já reflete o tema do tenant**).
- ✅ **Home logada** — próximos, histórico, meus dados, clube (placeholder).
- ✅ **Wizard de agendar** — fluxo completo, motor/concorrência sólidos (1C).
- 🎨 **(corrigido nesta etapa)** Componentes compartilhados `x-ng.option-card` e
  `x-ng.empty` usavam `bg-white`/`zinc` fixos (e variantes `dark:` que **não
  disparam no portal**, pois ele não aplica `.dark`). No tema padrão (todos os
  presets têm superfície branca) "passava"; com superfície **custom escura** os
  cartões do wizard ficavam brancos = off-brand. Ver [[Identidade Visual do Estabelecimento (Tema)]].
- 🎨 **(corrigido)** Cancelamento usava `wire:confirm` (confirm **nativo** do
  browser) — o design system pede modal bonito. Agora é `flux:modal`.
- 🎨 **(elevado)** Sem transição entre passos do wizard; faltava hierarquia/realce
  no resumo e na grade de horários.

## Painel do dono (auditoria; **não** alterado nesta etapa)
> Passada mais leve (render smoke + testes), pois o foco desta etapa é o portal.
- ✅ **Login** — split com tema do tenant.
- ✅ **Dashboard** — renderiza indicadores; gráficos Chart.js aparecem com dados e
  caem em **empty-state** ("Sem dados no período") quando não há — não está quebrado.
  Faturamento é ESTIMADO (ver [[Dashboard do Dono]]).
- ✅ **Kanban** — renderiza quadros/colunas; DnD via SortableJS (JS).
- ✅ **Cadastros** (unidades/serviços/equipe/horários/papéis/bloqueios) e **Agenda**
  — cobertos por testes (1B/1D); não re-auditados tela a tela nesta rodada.
- ✅ **Aparência/Tema** e **Onboarding** (`/admin/...`) — existem (editor + prévia;
  wizard de 5 etapas).
- 🎨 **Pendente (Etapa 6):** aplicar a identidade do tenant também no **painel** e
  nas telas de **auth** de forma ampla (hoje o painel usa só o acento). Dashboard e
  kanban entram em rodadas próprias de polimento.

## Lição (anti-divergência teste×navegador)
`Livewire::test()` verde **não** garante boa aparência: ele não exercita tema/CSS
nem o HTTP real. Por isso a auditoria rendeu o HTML com **superfície escura custom**
— foi o que revelou os cartões brancos fixos. Manter esse hábito ao auditar UI.
