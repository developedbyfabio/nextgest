---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-21
tags: [nextgest, kanban, crm, atendimento, livewire]
---

# Kanban (Atendimento e CRM)

> Projeto: [[Nextgest - Visão Geral]] · Decisão: [[Decisões de Arquitetura]] (D22) ·
> Etapa 5 da evolução visual.

## O que é
Dois quadros kanban no painel do tenant:

- **Atendimento** (balcão/fila do dia) — cartões podem ligar a um agendamento.
- **CRM** (funil de leads/tarefas) — cartões com cliente, responsável, valor estimado.

Componente: `App\Livewire\Painel\Kanban\Index` (rota `painel.kanban`). Os dois quadros
e suas colunas são **semeados no tenant** pelo `TenantDatabaseSeeder` (idempotente):

- Atendimento: `Aguardando`, `Em atendimento`, `Concluído`, `Pago`.
- CRM: `Novo contato`, `Em conversa`, `Agendado`, `Fidelizado`.

## Permissões
- **`ver_kanban_atendimento`** — acessar o quadro de Atendimento (inclui **Recepção**).
- **`gerir_kanban`** — quadro CRM e **editar a estrutura** (colunas). Dono/Gerente.
- Cartões: quem acessa o quadro gere seus cartões.

## Interação
- **Drag-and-drop** via **SortableJS**, arrastado pelo **handle** (`[data-kanban-handle]`):
  placeholder no destino (`.ng-kanban-ghost`), elevação do cartão arrastado
  (`.ng-kanban-drag`), animação suave.
- **Persistência robusta:** `moverCartao(id, coluna, ordem)` persiste coluna+ordem
  (`reindexar`). Update **otimista** no cliente; se o servidor recusar, o JS
  **reverte** o cartão à posição de origem e mostra toast — board e banco nunca
  divergem. Alternativa acessível: menu **"Mover para"**.
- Menu **"Mover para"** acessível (o destino atual fica desabilitado via `:disabled`).
- **Modais** (`flux:modal`) para criar/editar coluna e cartão e para **confirmar**
  arquivar/remover — **sem `confirm` nativo**.

> [!note] Gotcha de UI
> Nos botões/itens Flux deste módulo, condicionais usam `:disabled="cond"` — a
> diretiva `@disabled` quebra a compilação em componente Flux. Ver
> [[Bug - Flux disabled quebra Blade]].

## Elevação visual (Etapa C, 2026-06-21)
- **Tema da marca** nos dois quadros: colunas em `.ng-surface-muted`, cartões em
  `.ng-surface`, cabeçalho de coluna com **acento** + **contador** temático.
  **Dark-safe** (verificado em superfície custom escura: 0 `bg-white`/`zinc` fixos no
  markup do módulo; o resto é Flux, que acompanha via `.dark` do shell — Etapa B).
- **Estados:** skeleton de colunas ao trocar de quadro / "gerar do dia"; coluna vazia
  com orientação ("arraste cartões para cá"); erro recuperável no arraste (revert).
- **Responsivo:** rolagem horizontal com **snap** entre colunas no celular.
- **"Excluir" = arquivar:** `removerCartao` faz **soft delete** (`SoftDeletes` em
  `KanbanCartao`, coluna `deleted_at`). O cartão sai do board mas é preservado.
  Remover **coluna** segue estrutural (hard delete, com modal de confirmação).
- Ver [[Padrao de UI-UX (Design System)]], [[Identidade Visual do Estabelecimento (Tema)]]
  e [[Auditoria de UI (Portal e Painel)]].

## Modelo
- `kanban_quadros` (tipo: `atendimento` | `crm`), `kanban_colunas` (ordem),
  `kanban_cartoes` (titulo, ordem, `cliente_id`, `responsavel_user_id`,
  `agendamento_id`, `valor_estimado`, descrição, **`deleted_at`** — soft delete).
- Models: `KanbanQuadro`, `KanbanColuna`, `KanbanCartao` (usa `SoftDeletes`).

## Relacionado
- [[Decisões de Arquitetura]] (D22)
- [[Padrao de UI-UX (Design System)]]
