# Nextgest — Prompt Dev 1B: Cadastros do dono

> Cole para o Claude root do servidor em `/srv/www/nextgest`. Continua após a 1A.
> Commits pequenos, reporte ao fim. Sem modo tutorial: maior padrão de qualidade.
> Ambiente é local de testes (sem DNS/SSL); nada de produção aqui.
> Ver [[Decisões de Arquitetura]] e [[Prompt Dev 1A - Autenticacao e Layout]].

---

## Objetivo
No painel da equipe, permitir cadastrar e gerenciar o que o agendamento precisa:
unidades, serviços, equipe (profissionais), vínculos e horários de trabalho.
Tudo respeitando as permissões (spatie).

## Regras transversais
- **Permissões no servidor** (gates/policies), não só escondendo botões na UI.
  Dono e Gerente gerenciam; Recepção e Profissional não criam serviços/equipe.
- **Não excluir fisicamente**: "excluir" = inativar (`ativo=false`). Exclusão
  definitiva só com justificativa explícita do humano.
- Validação completa nos formulários (Flux + Livewire), feedback claro.
- Multi-unidade: se houver só 1 unidade, a UI simplifica (não força escolher
  filial); com 2+, a filial aparece nos vínculos.

## Tarefas
1. **Unidades** — CRUD (nome, endereço, telefone, ativo). Inativar em vez de
   excluir. Permissão: gerir_unidades (Dono/Gerente).
2. **Serviços** — CRUD (nome, descrição, duração em minutos, preço, ativo) +
   seleção de em quais **unidades** o serviço é oferecido (`servico_unidade`).
   Permissões `criar_servico`/`editar_servico`.
3. **Equipe (users)** — criar/editar membro: nome, e-mail, papel (Dono, Gerente,
   Recepção, Profissional ou papel customizado), `e_profissional`, senha inicial
   definida pelo gestor, vínculo com unidades (`user_unidade`). Para
   profissionais, vincular os **serviços** que sabe fazer (`servico_user`).
   Inativar em vez de excluir. Permissões `criar_usuario`/`editar_usuario`.
4. **Horários de trabalho** — por profissional e unidade: faixas semanais
   (`horarios_trabalho`), permitindo **múltiplas faixas por dia** (ex.: 09:00–12:00
   e 13:00–18:00) para representar o intervalo de almoço. UI semanal.
5. **Papéis e permissões** — tela (permissão `editar_permissoes`, padrão Dono):
   editar as permissões de cada papel e criar papéis personalizados (spatie).
   Pode ser commit separado dentro da 1B.
6. **Testes (Pest)** — CRUDs felizes e de erro; e checagem de permissão (ex.:
   Profissional recebe 403 ao tentar criar serviço; Recepção não cria equipe).
7. **Docs** — atualizar `docs/` com a 1B (telas, permissões, vínculos).

## Fora de escopo (próximas)
- **Bloqueios pontuais** (folga/feriado) e o **cálculo de disponibilidade** vêm
  na 1C, junto do fluxo de agendamento.
- Produtos, clube, pagamentos, kanban, WhatsApp — blocos próprios depois.

## Suposições (não bloqueiam)
- Senha inicial da equipe definida pelo gestor (convite por e-mail só quando o
  MAIL_* estiver configurado).
- "Excluir" significa inativar em todos os cadastros.

## Ao terminar
Reportar: telas/rotas criadas, permissões aplicadas, como cadastrar um
profissional completo (com serviços e horários) no `barbeariateste`, e o que
ficou para a 1C.

---

## Plano restante da fatia de agendamento
- **1C** — Portal do cliente: agendar (filial → serviço → profissional → horário),
  bloqueios pontuais e cálculo de disponibilidade, validação de conflito.
- **1D** — Agenda da equipe: visualizar e mudar status dos agendamentos.
