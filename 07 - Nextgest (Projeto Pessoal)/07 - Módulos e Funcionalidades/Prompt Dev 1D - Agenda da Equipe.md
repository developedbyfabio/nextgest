# Nextgest — Prompt Dev 1D: Agenda da equipe

> Cole para o Claude root do servidor em `/srv/www/nextgest`. Continua após a 1C.
> Commits pequenos, reporte ao fim. Ambiente local de testes. Reuse o
> `MotorDisponibilidade` e o `Agendador` da 1C — não reimplemente regra de horário.
> Ver [[Padrao de UI-UX (Design System)]] e [[Decisões de Arquitetura]] (D27).

---

## Definition of Done de UI (obrigatório nesta e em toda fatia)
Cumprir o padrão [[Padrao de UI-UX (Design System)]]:
componentização (Flux), modais/slide-overs com transição, micro-interações,
estados de carregando/vazio/erro/sucesso, toasts, navegação tipo SPA
(`wire:navigate`), dark mode, responsivo e acessível, efeitos só em
transform/opacity. Nada de tela crua.

## Objetivo
No painel da equipe, visualizar e gerenciar a agenda: ver agendamentos, criar
manual, mudar status, cancelar/remarcar. Profissional vê só a própria agenda.

## Permissões
- `ver_agenda` (Gerente/Recepção/Dono): vê a agenda de todos.
- `ver_agenda_propria` (Profissional): vê só os próprios agendamentos.
- `gerir_agenda`: criar manual, mudar status, cancelar/remarcar.

## Telas
1. **Agenda** (`painel.agenda`): visão de dia e semana, por profissional e/ou
   unidade. Grade de horários com os agendamentos posicionados; badges de status
   coloridos. Filtros (profissional, unidade, status, data). Para o Profissional,
   a home do painel já abre na agenda do dia dele.
2. **Detalhe do agendamento** (slide-over): cliente, serviços, profissional,
   horário, valor, status; ações conforme permissão.
3. **Novo agendamento manual** (modal/slide-over): selecionar cliente existente
   (busca com debounce) ou criar rápido (nome + telefone); depois unidade →
   serviços → profissional → dia/horário usando o `MotorDisponibilidade`.
   `origem=equipe`, `criado_por_user_id` preenchido. Passa pelo `Agendador`
   (lock/transação) igual ao portal.

## Regras de status
- Transições: pendente → confirmado → em_andamento → concluido; e
  cancelado / nao_compareceu a partir de onde fizer sentido.
- Mudança de status via ação com confirmação quando destrutiva (cancelar).
- Cancelar libera o horário. **Remarcar** = revalidar novo horário pelo
  `MotorDisponibilidade` dentro do `Agendador` (lock) — sem furar concorrência.

## Segurança/qualidade
- Tudo revalidado no servidor (mesmas regras da 1C). Profissional não acessa
  agenda de outros. Toda ação reconfere permissão (`$this->authorize()`).

## Testes (Pest)
- Profissional vê só a própria agenda (não vê a de outro); Recepção vê todas.
- Criar manual cria com `origem=equipe` e snapshots corretos; passa pela
  validação de conflito (reusa teste de concorrência).
- Transições de status válidas/ inválidas; cancelar libera horário; remarcar
  revalida e não duplica.

## Fora de escopo (próximas fatias)
- Produtos/vendas (fatia 2), clube (3), pagamentos (4), etc.
- Arrastar-e-soltar na agenda pode ficar para um polimento posterior (não é
  requisito agora; se incluir, manter acessível e performático).

## Ao terminar
Reportar: telas/rotas, como a agenda se comporta por papel, como criar um
agendamento manual no `barbeariateste`, e como o status/remarcação foram tratados.
