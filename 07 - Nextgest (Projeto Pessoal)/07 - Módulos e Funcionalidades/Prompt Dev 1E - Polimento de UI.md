# Nextgest — Prompt Dev 1E: Polimento de UI da Fatia 1

> Rodar **depois** que a 1D estiver concluída. Cole para o Claude root em
> `/srv/www/nextgest`. Commits pequenos por área. **Não** alterar regras de
> negócio nem schema — é só UI/UX. Manter todos os testes verdes (ajustar
> seletores se necessário, sem afrouxar asserções de regra).
> Ver [[Padrao de UI-UX (Design System)]] e [[Decisões de Arquitetura]] (D27).

---

## Objetivo
Elevar todas as telas das fatias 1A–1D ao padrão [[Padrao de UI-UX (Design System)]]
(D27). Resultado: cara de produto comercial robusto, não de protótipo. Nada de
tela crua.

## Escopo (telas a polir)
- Auth: login da equipe, login/registro do cliente, login do admin.
- Layouts: painel (sidebar) e portal do cliente (mobile-first).
- Cadastros (1B): unidades, serviços, equipe, horários, papéis/permissões.
- Bloqueios (1C) e portal: home + wizard de agendamento.
- Agenda da equipe (1D).

## Tarefas
1. **Tema e tokens centralizados:** definir uma paleta moderna e coesa para a
   marca Nextgest (propor; manter em tokens para troca fácil), tipografia,
   espaçamento, raios, sombras sutis. **Dark mode** completo e consistente.
2. **Biblioteca de componentes compartilhados** (Flux + Blade/Livewire):
   botões (variações), inputs/label/erro inline, select/combobox com busca,
   card, tabela padrão (busca + ordenação + paginação + empty state), modal,
   slide-over, badge de status, toast, tabs, avatar, tooltip, breadcrumb.
   Refatorar as telas para usar esses componentes (sem HTML cru solto).
3. **CRUDs (1B)**: criar/editar em modal ou slide-over com transição; inativar
   com modal de confirmação; toasts de sucesso/erro; `wire:loading` com skeleton.
4. **Wizard de agendamento (portal, mobile-first)**: passos em **tela cheia**
   com indicador de progresso e transições; serviços e profissionais em cards
   selecionáveis; seletor de dia/horário caprichado; estado de carregando dos
   horários; mensagem amigável de slot indisponível; tela de confirmação.
5. **Agenda da equipe (1D)**: grade com transições suaves, badges de status,
   slide-over de detalhe, modal de novo agendamento.
6. **Auth**: layout cuidado (split com a marca), validação inline, estados de
   erro/carregando, feedback de throttle.
7. **Navegação SPA**: `wire:navigate` em painel e portal; estados ativos na
   sidebar; barra de progresso de navegação.
8. **Acessibilidade e performance**: foco visível, ARIA, contraste,
   `prefers-reduced-motion`; animar só transform/opacity; sem reflow; sem
   regressão de testes.
9. **Docs**: documentar o design system e os componentes em `docs/`.

## Definition of Done
Cada tela do escopo passa no checklist do padrão: componentes do design system,
estados (loading/vazio/erro/sucesso), modais/slide-overs com transição,
responsivo + acessível + dark mode, micro-interações de hover/focus, performance
verificada. Testes (1A–1D) seguem verdes.

## Ao terminar
Reportar: tema/tokens criados e como trocá-los, lista de componentes
compartilhados, telas refatoradas, e confirmação de que a suíte de testes
continua passando.
