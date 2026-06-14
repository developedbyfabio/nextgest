# 11 — Agenda da equipe (fatia 1D)

Visualização e gestão da agenda no painel, reusando o `MotorDisponibilidade` e o
`Agendador` da 1C (sem reimplementar regra de horário).

## Rotas/telas

- `painel.agenda` (`/{tenant}/painel/agenda`): visões **dia** e **semana**,
  filtros (profissional, unidade, status, data), navegação anterior/hoje/próximo.
  Cada agendamento abre um **slide-over** de detalhe com ações.
- A home do painel (`painel.dashboard`) **redireciona o Profissional** (que só
  tem `ver_agenda_propria`) direto para a agenda.
- Novo agendamento manual: componente `painel.agenda.novo-agendamento` (modal),
  embutido na agenda; só aparece para quem tem `gerir_agenda`.

## Permissões / escopo por papel

- `ver_agenda` (Dono/Gerente/Recepção): vê a agenda de **todos**; pode filtrar
  por profissional.
- `ver_agenda_propria` (Profissional): vê **só os próprios** agendamentos (o
  filtro de profissional é fixado nele e oculto).
- `gerir_agenda`: criar manual, mudar status, cancelar e remarcar.

O acesso à página exige `ver_agenda` **ou** `ver_agenda_propria` (checado em
`mount`); cada ação de escrita reconfere `gerir_agenda` (`$this->authorize`).
Toda query é escopada por papel no servidor — o Profissional não acessa a agenda
de outro mesmo manipulando ids.

## Novo agendamento manual

Fluxo: cliente (busca com debounce **ou** cadastro rápido nome+telefone) →
(unidade, se 2+) → serviços → profissional/"sem preferência" → dia/horário
(`MotorDisponibilidade`) → confirmar. Grava via
`Agendador::agendarPelaEquipe()` com `origem=equipe` e `criado_por_user_id`,
passando pelo mesmo lock/transação do portal (anti-duplicidade). Ao concluir,
dispara `agenda-atualizada` para a agenda recarregar.

## Status e remarcação

Transições permitidas (`Agendamento::TRANSICOES`):

- pendente → confirmado, em_andamento, concluido, cancelado, nao_compareceu
- confirmado → em_andamento, concluido, cancelado, nao_compareceu
- em_andamento → concluido, cancelado
- concluido / cancelado / nao_compareceu → (finais)

A UI mostra só os botões das transições válidas; **cancelar** pede confirmação e
libera o horário. `Agendador::mudarStatus()` valida a transição (senão
`TransicaoInvalidaException`).

**Remarcar** (`Agendador::remarcar()`): escolhe novo dia/horário; revalida pelo
`MotorDisponibilidade` com **lock pessimista** no profissional, **ignorando o
próprio** agendamento na checagem de conflito (`intervaloAgendavel` /
`slots(..., ignorarAgendamentoId:)`); mantém a duração (snapshot dos itens). Não
fura concorrência.

## Reuso da 1C (sem duplicar regra)

- `Agendador::confirmar()` (cliente) e `agendarPelaEquipe()` (equipe) chamam o
  mesmo núcleo `criar()` (lock + revalidação + snapshots).
- `MotorDisponibilidade` ganhou `intervaloAgendavel()` e o parâmetro
  `ignorarAgendamentoId` em `slots()`/`slotValido()`/`intervalosOcupados()` para
  a remarcação.

## Testes (`tests/Feature/Painel/AgendaTest.php` e `NovoAgendamentoTest.php`)

Profissional vê só a própria agenda; Recepção vê todas; transição válida/ inválida;
cancelar libera o horário; remarcar move sem duplicar; criar manual grava
`origem=equipe`+`criado_por_user_id` com snapshots; concorrência (slot tomado no
wizard → não duplica); `gerir_agenda` exigido para o agendamento manual. 74
testes no total.

## Observações

- A visão semana usa chips por dia (borda colorida por status). Arrastar-e-soltar
  ficou fora de escopo (polimento futuro).
- Variável de view não pode se chamar `slots` (colide com o SlotProxy do
  Livewire 4) — usa-se `horarios`/`horariosRemarcar`.
