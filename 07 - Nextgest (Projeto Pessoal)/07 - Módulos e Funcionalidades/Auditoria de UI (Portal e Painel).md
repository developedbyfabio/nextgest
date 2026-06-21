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

> [!important] Modelo de tema atualizado (Etapa D, D36)
> Esta nota foi escrita nas Etapas A/B, quando as **superfícies** seguiam a cor da
> marca e o `.dark` era forçado por luminância. Isso foi **substituído**: agora a
> marca é só **acento + logo + tipografia** e as superfícies seguem o **modo
> claro/escuro/sistema** do Flux. Onde abaixo se lê "superfície/dark-safe da marca",
> entenda "superfície pelo modo". Ver [[Decisões de Arquitetura]] D36.

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

## Painel do dono
> Etapa A: auditoria. **Etapa B (2026-06-21): shell + auth tematizados e dashboard
> elevado.**
- ✅ **Login** — split com tema do tenant; **dark-safe** (`.dark` automático).
- ✅ **(elevado na Etapa B) Shell do painel** — sidebar/topbar/logo/títulos refletem a
  identidade completa do tenant (`cssVars()`); modo escuro do Flux ligado quando a
  superfície da marca é escura. Verificado em **superfície custom escura**: 0
  `bg-white`/`zinc` fixos no nosso markup; Flux acompanha via `.dark`.
- ✅ **(elevado na Etapa B) Dashboard** — KPIs/gráficos em `.ng-surface` (dark-safe),
  Chart.js nas cores da marca, estados loading/vazio/erro, filtros polidos,
  responsivo. Faturamento ESTIMADO. Ver [[Dashboard do Dono]].
- ✅ **(elevado na Etapa C) Kanban** — colunas/cartões na superfície da marca
  (dark-safe), DnD com handle/placeholder/elevação e **revert em falha**,
  confirmações por `flux:modal` (sem `confirm` nativo), "excluir" = arquivar (soft
  delete), estados (skeleton/vazio/erro) e responsivo com snap. Ver
  [[Kanban (Atendimento e CRM)]].
- ✅ **Cadastros** (unidades/serviços/equipe/horários/papéis/bloqueios) e **Agenda**
  — cobertos por testes (1B/1D); herdam o `.dark` correto; polimento temático fino
  pendente (rodadas futuras).
- ✅ **Aparência/Tema** e **Onboarding** (`/admin/...`) — existem (editor + prévia;
  wizard de 5 etapas).
- 🎨 **Pendente:** polimento temático fino das **telas internas** (agenda/cadastros —
  hoje herdam o `.dark` correto, mas sem acabamento de marca como portal/dashboard/
  kanban); possível override de claro/escuro por usuário da equipe. Próxima frente
  grande: **Fatia 2** (produtos/vendas).

## Lição (anti-divergência teste×navegador)
`Livewire::test()` verde **não** garante boa aparência: ele não exercita tema/CSS
nem o HTTP real. Por isso a auditoria rendeu o HTML com **superfície escura custom**
— foi o que revelou os cartões brancos fixos. Manter esse hábito ao auditar UI.
