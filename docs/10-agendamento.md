# 10 — Portal de agendamento do cliente (fatia 1C)

O cliente logado agenda pelo portal mobile-first: (filial →) serviços →
profissional → dia → horário → confirmar. Inclui o motor de disponibilidade,
bloqueios pontuais e prevenção de horário duplicado.

## Fuso e configurações

- `APP_TIMEZONE=America/Sao_Paulo` (config/app.php passou a ler `env`). Datas
  tratadas de forma consistente nesse fuso (fuso por tenant fica para depois).
- Config keys (seeder, por tenant): `intervalo_slots_minutos=15`,
  `cancelamento_antecedencia_horas=2`, `confirmacao_automatica=1`. Lidas via
  `App\Models\Configuracao::valor()/inteiro()/booleano()`.

## A. Bloqueios pontuais (painel)

- `painel.bloqueios` (`/{tenant}/painel/bloqueios`), permissão **gerir_agenda**
  (adicionada ao seeder; Dono/Gerente/Recepção).
- CRUD de `bloqueios` por profissional (início, fim, motivo). Excluir um bloqueio
  apenas libera o horário (evento operacional, não há inativação).

## B. Motor de disponibilidade — `App\Services\Agendamento\MotorDisponibilidade`

`slots(unidadeId, servicoIds[], profissionalId|null, Carbon $data)` → coleção de
`['hora' => 'HH:MM', 'profissional_id' => int, 'inicio' => Carbon]`.

Regras:
1. **Duração total** = soma de `servicos.duracao_minutos`.
2. **Janelas**: `horarios_trabalho` do profissional naquela unidade e dia da
   semana — várias faixas/dia; o slot precisa caber inteiro em **uma** faixa
   (respeita o almoço).
3. **Subtrai** agendamentos ocupantes (status ≠ cancelado/nao_compareceu) e
   `bloqueios` sobrepostos.
4. **Slots** a cada `intervalo_slots_minutos`; válido se `[início, início+duração]`
   couber na faixa e não colidir.
5. Não oferta horário **no passado** (para hoje); datas passadas não retornam nada.
6. **Sem preferência**: considera todos os profissionais que fazem **todos** os
   serviços na unidade e oferta, por horário, o primeiro disponível.

`slotValido(...)` revalida um início específico (usado na confirmação).

## C. Fluxo no portal (guard cliente, mobile-first)

- **Home** `/{tenant}`: "Próximos agendamentos" (com cancelar quando permitido) e
  botão "Novo agendamento". Clube fica como placeholder.
- **Wizard** `/{tenant}/agendar` (`App\Livewire\Portal\Agendar`):
  - **Filial**: só aparece com 2+ unidades (1 → automática).
  - **Serviços**: multi-seleção (duração e preço, com total).
  - **Profissional**: só quem faz **todos** os serviços e atende a unidade +
    "sem preferência".
  - **Dia/horário**: seletor de data + grade de horários (motor B).
  - **Confirmar**: cria `agendamentos` + `agendamento_servico` com **snapshot**
    de preço/duração; `valor_total` e `data_hora_fim` calculados; `origem=cliente`;
    `status` = `confirmado` se `confirmacao_automatica`, senão `pendente`.
- **Cancelar**: respeita `cancelamento_antecedencia_horas`; vira `status=cancelado`
  e o horário volta a ficar livre. (Reagendar = cancelar e remarcar, por ora.)

## D. Prevenção de horário duplicado — `App\Services\Agendamento\Agendador`

`confirmar(...)` roda numa **transação** e adquire **lock pessimista** na linha
do profissional (`users.id` `FOR UPDATE`) **antes** de revalidar o slot. Isso
serializa tentativas simultâneas para o mesmo profissional — diferente de travar
linhas de `agendamentos` (que não impediria a inserção quando ainda não há
agendamento no horário). O segundo cliente, ao obter o lock, já enxerga o
agendamento do primeiro e a revalidação falha → `SlotIndisponivelException`. O
portal mostra mensagem amigável e recarrega os horários.

## E. Segurança

Tudo revalidado no servidor na confirmação: serviços pertencem à unidade,
profissional faz os serviços e atende a unidade, slot cabe na janela, não está no
passado, sem colisão. Cliente só agenda/cancela o que é dele (ownership por
`cliente_id`). CSRF ativo.

## Como agendar de ponta a ponta no `barbeariateste`

1. `php artisan nextgest:criar-dono barbeariateste` e entre no painel.
2. **Unidades** → crie uma unidade. **Serviços** → crie um serviço (marque a
   unidade). **Equipe** → crie um Profissional (marque "É profissional" e o
   serviço); em **Horários**, adicione faixas (ex.: seg–sex 09:00–18:00).
3. (Opcional) **Bloqueios** → cadastre uma folga.
4. No portal `/{barbeariateste}` → **Criar conta** (cliente) → **Novo
   agendamento** → serviço → profissional/sem preferência → dia → horário →
   **Confirmar**. O agendamento aparece em "Próximos agendamentos".

## Testes (`tests/Feature/Agendamento` e `tests/Feature/Portal`)

Motor: janela − agendamentos − bloqueios; almoço (duas faixas); duração que não
cabe perto do fim; passado oculto; "sem preferência"; exige fazer todos os
serviços. Agendador: snapshots/total/status, **concorrência** (slot tomado →
falha, sem duplicar), passado, cancelamento dentro/fora da antecedência. Portal:
exige login, agenda fim-a-fim, não duplica no wizard, cancelar próprio e bloqueio
de cancelar de outro. 65 testes no total.

## Fora de escopo (1D)

Agenda da equipe (ver/gerenciar agendamentos, criar manual, mudar status, marcar
nao_compareceu) reusando o mesmo motor.
