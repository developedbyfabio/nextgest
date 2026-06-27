---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-27
tags: [nextgest, painel, agenda, toast, tempo-real]
---

# Aviso de próximo atendimento

> Projeto: [[Nextgest - Visão Geral]] · Decisão: [[Decisões de Arquitetura]] (**D69**) ·
> Padrão de UI: [[Padrao de UI-UX (Design System)]] (toast).

## O que é
Quando o **profissional logado** tem um atendimento "a atender" começando em **≤ 15 minutos**, aparece
um **toast** (o mesmo do "Assinante adicionado") que **some sozinho**, com cliente, hora e serviço:
*"Seu próximo atendimento está chegando — [Cliente] · [HH:MM] · [Serviço]"*.

## Como funciona
- **Componente global** `App\Livewire\Painel\AvisoProximoAtendimento` (view só com o gatilho), embutido
  no **layout do painel** — mas **só montado para `e_profissional`** (`@if` no layout): quem não é
  profissional nem carrega o componente (zero polling/queries).
- **Tempo real = polling Livewire leve** (`wire:poll.60s`) + 1ª checagem imediata por **`wire:init`**.
  Sem WebSocket/broadcasting.
- **`verificar()`**: busca o próximo atendimento do profissional com status a atender
  (`whereNotIn('status', ['concluido','cancelado','nao_compareceu'])`) e início em `(agora, agora+15min]`
  (fuso `APP_TIMEZONE`; índice composto `(profissional_id, data_hora_inicio)`). Achou e ainda não
  avisado → `Flux::toast(...)`.
- **Idempotência:** ids avisados na **sessão** (`aviso_proximo:<userId>`) — uma vez por agendamento,
  sem repetir a cada poll nem entre navegações.

## Por que `wire:init`, não `mount()`
Telas que **redirecionam no `mount`** (o Dashboard manda o profissional para a agenda) ainda renderizam
o layout/este componente antes do redirect. Rodar a checagem no `mount` "consumia" o aviso numa página
**descartada** (marcava a sessão e o toast se perdia). `wire:init` roda **no cliente**, só em páginas
realmente exibidas → o toast aparece de verdade. (Bug pego na validação e corrigido.)

## Limites
Só **LÊ** a agenda — **não** toca o `MotorDisponibilidade`, o fluxo de atendimento, faturamento ou RBAC.
Público: **só o profissional daquele atendimento** (query força `profissional_id = auth id`); Dono/Gerente
que não é o profissional não vê (e se não é `e_profissional`, o componente nem monta).

## Testes
`tests/Feature/Painel/AvisoProximoAtendimentoTest.php` (7): dispara na janela; idempotente (sessão);
marca na sessão; fora da janela não dispara; status encerrado não dispara; não-profissional não dispara;
só o profissional daquele atendimento. Suíte verde (561/561). **Dev — sem deploy.**
