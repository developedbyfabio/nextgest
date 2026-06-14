# 02 — Agendamento

Núcleo do sistema. Migration: `database/migrations/tenant/2026_06_14_190001_create_agendamento_core_tables.php`.

## Tabelas

- **unidades** — filiais. Tudo físico (agenda, estoque) pendura numa unidade.
- **users** — equipe interna (guard `web`, `HasRoles`). `e_profissional`,
  `ativo`.
- **clientes** — clientes finais (guard `cliente`). `email` único nullable,
  `telefone`, `password` nullable.
- **servicos** — `duracao_minutos`, `preco`, `ativo`.
- **servico_unidade** — em quais filiais o serviço é oferecido (pivô).
- **servico_user** — quais profissionais executam quais serviços (pivô).
- **user_unidade** — em quais filiais o profissional atende (pivô).
- **horarios_trabalho** — disponibilidade recorrente (`dia_semana` 0–6,
  `hora_inicio`, `hora_fim`).
- **bloqueios** — folgas/exceções (`inicio`, `fim`, `motivo`).
- **agendamentos** — `unidade_id`, `cliente_id`, `profissional_id`,
  `data_hora_inicio/fim`, `status` (pendente, confirmado, em_andamento,
  concluido, cancelado, nao_compareceu), `origem` (cliente/equipe),
  `valor_total` (snapshot). Índice `(profissional_id, data_hora_inicio)`.
- **agendamento_servico** — itens com **snapshot** de `preco` e
  `duracao_minutos`.
- **configuracoes** — chave/valor. Semeado: `confirmacao_automatica=1`.

## Regras (a implementar)

- **Sem sobreposição**: dois agendamentos do mesmo profissional não podem
  ocupar o mesmo intervalo (validação no código; índice ajuda a checar).
- **Horários livres** = janela de `horarios_trabalho` − agendamentos − `bloqueios`.
- **Confirmação configurável** (D11): agendamento do cliente entra `confirmado`
  (padrão) ou `pendente`, conforme `configuracoes.confirmacao_automatica`.
- **Multi-serviço, um profissional** (D07): vários `agendamento_servico` para um
  único `profissional_id`. `data_hora_fim` = início + soma das durações.

## Portal do cliente

Mobile-first (D10): filial → serviço → profissional → dia/horário. Ao escolher o
serviço, só aparecem os profissionais ligados a ele (`servico_user`).
