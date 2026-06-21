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
- **Drag-and-drop** via **SortableJS** (persiste coluna + ordem).
- Menu **"Mover para"** acessível (alternativa ao arraste; o destino atual fica
  desabilitado via `:disabled`).
- Modais para criar/editar coluna e cartão.

> [!note] Gotcha de UI
> Nos botões/itens Flux deste módulo, condicionais usam `:disabled="cond"` — a
> diretiva `@disabled` quebra a compilação em componente Flux. Ver
> [[Bug - Flux disabled quebra Blade]].

## Modelo
- `kanban_quadros` (tipo: `atendimento` | `crm`), `kanban_colunas` (ordem),
  `kanban_cartoes` (titulo, ordem, `cliente_id`, `responsavel_user_id`,
  `agendamento_id`, `valor_estimado`, descrição).
- Models: `KanbanQuadro`, `KanbanColuna`, `KanbanCartao`.

## Relacionado
- [[Decisões de Arquitetura]] (D22)
- [[Padrao de UI-UX (Design System)]]
