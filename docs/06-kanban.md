# 06 — Kanban

Migration: `database/migrations/tenant/2026_06_14_190005_*`.

Dois tipos de quadro (D22):

- **atendimento** — fila do dia; o cartão liga a um agendamento.
- **crm** — leads/tarefas com responsável e prazo.

## Tabelas

- **kanban_quadros** — `nome`, `tipo` (atendimento/crm), `unidade_id` (nullable),
  `ativo`.
- **kanban_colunas** — `quadro_id`, `nome`, `ordem`.
- **kanban_cartoes** — `coluna_id`, `titulo`, `descricao`, `ordem`,
  `cliente_id`, `agendamento_id`, `responsavel_user_id`, `valor_estimado`,
  `prazo`. Índice `(coluna_id, ordem)` para drag-and-drop.

## Regras (a implementar)

- Reordenação por `ordem` dentro da coluna; mover cartão entre colunas.
- Quadro de atendimento pode ser populado a partir dos agendamentos do dia.
