# Nextgest — Prompt Dev 1C: Portal de agendamento do cliente

> Cole para o Claude root do servidor em `/srv/www/nextgest`. Continua após a 1B.
> Commits pequenos, reporte ao fim. Maior padrão de qualidade. Ambiente local de
> testes (sem DNS/SSL). É a fatia mais sensível: capriche em disponibilidade e
> concorrência. Ver [[Decisões de Arquitetura]] e [[Prompt Dev 1B - Cadastros do Dono]].

---

## Objetivo
O cliente logado agenda pelo portal mobile-first: (filial →) serviços →
profissional → dia → horário → confirmar. Inclui o cálculo de horários livres,
os bloqueios pontuais e a prevenção de horário duplicado.

---

## A. Bloqueios pontuais (painel)
- CRUD simples de `bloqueios` por profissional (inicio, fim datetime, motivo):
  folga, feriado, imprevisto. Permissão de agenda (ex.: `gerir_agenda`, adicionar
  ao seeder; Dono/Gerente/Recepção).

## B. Motor de disponibilidade (núcleo — testar muito)
Entrada: `unidade_id`, lista de `servico_id`, `profissional_id` (ou "sem
preferência"), data.
Regras:
1. **Duração total** = soma das `servicos.duracao_minutos` selecionados.
2. **Janelas de trabalho**: `horarios_trabalho` do profissional naquela unidade e
   dia da semana (podem ser várias faixas/dia — respeitar o almoço).
3. **Subtrair**: agendamentos existentes do profissional (status ≠ cancelado/
   nao_compareceu) e `bloqueios` que se sobreponham.
4. **Gerar slots** de início a cada `intervalo_slots_minutos` (config, padrão 15).
   Um slot é válido se `[inicio, inicio+duração_total]` couber inteiro numa janela
   e não colidir com nenhum agendamento/bloqueio.
5. Não ofertar horários no passado (para hoje).
6. "Sem preferência": considerar todos os profissionais que fazem os serviços na
   unidade e ofertar o primeiro disponível por slot.

## C. Fluxo no portal (mobile-first, guard cliente)
- Home `/{tenant}`: "Próximos agendamentos" e botão "Novo agendamento" (área do
  Clube fica como placeholder para depois).
- Wizard "Novo agendamento":
  - **Filial**: só aparece se houver 2+ unidades (senão, auto).
  - **Serviços**: multi-seleção (mostrar duração e preço; somar total).
  - **Profissional**: só os que fazem **todos** os serviços escolhidos **e**
    atendem naquela unidade; opção "sem preferência".
  - **Dia e horário**: seletor de data + lista de horários livres (motor B).
  - **Confirmar**: cria `agendamentos` + `agendamento_servico` com **snapshot** de
    preço e duração; `valor_total` e `data_hora_fim` calculados; `origem=cliente`.
    `status` = `confirmado` se `confirmacao_automatica=true`, senão `pendente`.
- O cliente vê e pode **cancelar** um agendamento futuro respeitando
  `cancelamento_antecedencia_horas` (config, padrão 2). Cancelar → `status=cancelado`
  e o horário volta a ficar livre. (Reagendar = cancelar e marcar de novo, por ora.)

## D. Prevenção de horário duplicado (crítico)
- Ao confirmar, dentro de uma **transação** do banco, **revalidar** que o slot
  ainda está livre **com lock** (lock pessimista nas linhas relevantes da agenda
  do profissional, ou lock equivalente), e só então inserir. Dois clientes
  tentando o mesmo horário ao mesmo tempo → apenas um conclui; o outro recebe
  mensagem amigável e a lista de horários é atualizada.

## E. Config e fuso
- Adicionar ao seeder as chaves `intervalo_slots_minutos=15` e
  `cancelamento_antecedencia_horas=2`.
- Definir `APP_TIMEZONE=America/Sao_Paulo`; tratar todas as datas de forma
  consistente. (Fuso por tenant fica como melhoria futura.)

## F. Testes (Pest)
- Motor de disponibilidade: janela menos agendamentos menos bloqueios; respeito ao
  almoço (duas faixas); duração que não cabe perto do fim; horários passados
  ocultos; "sem preferência".
- **Concorrência**: duas tentativas no mesmo slot → uma falha (sem duplicar).
- Confirmação cria snapshots corretos e `status` conforme `confirmacao_automatica`.
- Cancelamento dentro/fora da antecedência permitida; cliente só mexe nos próprios
  agendamentos.

## Segurança/qualidade
- Tudo validado no servidor: serviços pertencem à unidade, profissional faz os
  serviços, slot válido. Cliente só agenda/cancela o que é dele. CSRF ativo.

## Fora de escopo (próxima)
- **1D** — agenda da equipe (ver/gerenciar agendamentos, criar manual, mudar
  status, marcar nao_compareceu) reutilizando o mesmo motor.

## Suposições (não bloqueiam)
- Slot a cada 15 min; cancelamento até 2h antes; "sem preferência" disponível;
  fuso único America/Sao_Paulo; sem reagendamento direto (cancela e remarca).

## Ao terminar
Reportar: rotas/telas do portal, como agendar de ponta a ponta no `barbeariateste`,
como o motor calcula os horários, como a concorrência foi tratada, e o que ficou
para a 1D.
