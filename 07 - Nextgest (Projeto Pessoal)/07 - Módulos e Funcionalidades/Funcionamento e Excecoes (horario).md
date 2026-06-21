---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-22
tags: [nextgest, agenda, disponibilidade, funcionamento, excecoes, motor, feriados]
---

# Funcionamento do estabelecimento — horário semanal + exceções

> Projeto: [[Nextgest - Visão Geral]] · Motor: [[Modelo de Dados - Núcleo de Agendamento]]
> Disponibilidade: [[Prompt Dev 1C - Portal de Agendamento]] / [[Prompt Dev 1D - Agenda da Equipe]]

## Onde vive o horário (auditoria)
- **Horário semanal** = por **estabelecimento**, em `configuracoes` chave
  `horario_funcionamento` (JSON: `[{dia, aberto, inicio, fim}]`, `dia` 0=dom..6=sáb).
  Antes era escrito só pelo **onboarding** e **não** era lido pelo motor (informativo).
- **Janelas de trabalho** = `horarios_trabalho` por **profissional/unidade/dia** — é o que
  o `MotorDisponibilidade` usa para gerar slots (+ agendamentos + bloqueios).
- Faltava: editar o horário **no painel** e fazê-lo (mais as exceções) **afetar** o motor.

## A — Editar horário no painel
- Tela **`painel.funcionamento`** (`App\Livewire\Painel\Funcionamento\Index`, permissão
  **`gerir_agenda`** — Dono/Gerente/Recepção). Reusa o **mesmo** editor do onboarding,
  extraído como **`<x-funcionamento-editor>`** (toggles por dia + início/fim) — fonte de
  verdade única, sem markup paralelo. Salvar grava em `configuracoes.horario_funcionamento`.

## B — Calendário de exceções
- Tabela aditiva **`excecoes_funcionamento`** (por tenant): `data` (única), `tipo`
  (`fechado` | `horario_especial`), `hora_inicio`/`hora_fim` (no especial), `descricao`.
  Model `App\Models\ExcecaoFuncionamento`.
- **UI:** calendário mensal (Alpine + Livewire, sem deps), clica num dia **de hoje em
  diante** → fechado o dia todo **ou** horário especial; lista/edita/remove "próximas
  exceções". Tematizado.

## Integração com o MotorDisponibilidade (SEM reescrever)
Camada **`App\Services\Agendamento\Funcionamento::doDia($data)`** — injetada no motor —
retorna o estado do dia, combinando **exceção (precede)** + **horário semanal**:
- `fechado` → o motor retorna **zero** slots (e `intervaloAgendavel` recusa);
- `aberto [HH:MM, HH:MM]` → o motor **recorta** as janelas dos profissionais a essa faixa;
- `sem_config` → **permissivo**: sem horário configurado, o motor segue **idêntico** ao
  anterior (preserva tenants/testes sem horário; dias sem exceção inalterados).

O motor só ganhou: (1) injeção do `Funcionamento`, (2) early-return quando fechado,
(3) clamp da faixa ao gate, (4) checagem em `intervaloAgendavel`. O núcleo (geração de
slots, lock pessimista do `Agendador`) **não** mudou; bloqueios seguem funcionando;
agendamentos existentes não são afetados retroativamente.

## Prova (cadeia real, tenant de demo)
Dia de trabalho com **30 slots** de baseline → exceção **fechado** = **0 slots** →
**horário especial 10:00–11:00** = só `["10:00","10:15","10:30"]` → removida a exceção,
**volta a 30**. (Tarefa A: fechar o dia no horário semanal também zera os slots.)

## Testes
`tests/Feature/Agendamento/FuncionamentoTest.php` (9): sem config = permissivo; semanal
fechando o dia = 0 slots; exceção fechado = 0; especial = só na faixa; dia sem exceção
inalterado; `intervaloAgendavel` respeita fechado; a tela salva horário e o motor reflete;
cria/edita/remove exceção; permissão `gerir_agenda` (Profissional 403). Suíte **275 verde**.

## Relacionado
- [[Onboarding Guiado de Estabelecimento]] (define o horário inicial) ·
  [[Modelo de Dados - Núcleo de Agendamento]] (tabela de exceções).
