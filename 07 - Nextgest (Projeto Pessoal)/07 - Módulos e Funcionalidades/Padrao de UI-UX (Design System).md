---
projeto: Nextgest
tipo: padrao-ui-ux
status: vivo
criado: 2026-06-14
tags: [nextgest, ui, ux, design-system, frontend]
---

# Nextgest — Padrão de UI/UX e Design System

> Obrigatório em todas as telas. Todo prompt de desenvolvimento deve cumprir este
> padrão. Nada de tela "simples" ou crua. Cara de sistema comercial robusto.
> Ver [[Decisões de Arquitetura]] (D25, D27).

## Princípio
Robusto, tecnológico e impecável. Cada interação tem feedback; cada estado
(carregando, vazio, erro, sucesso) é tratado; cada ação importante acontece em
modal/slide-over com transição suave. O usuário deve sentir um software pronto,
não um protótipo.

## Design system
- Base Flux + Tailwind v4 com tema da marca (paleta, tipografia, espaçamento,
  raios, sombras sutis) centralizado em tokens. Tudo consistente.
- Tema claro/escuro.
- Componentização total: botões, inputs, selects, cards, tabelas, badges, tabs,
  avatares, tooltips — todos reutilizáveis e padronizados.
- Iconografia consistente (um único set).

## Interação e navegação
- Navegação com sensação de SPA (`wire:navigate`), sem recarregar a página toda.
- Micro-interações: transições suaves (Alpine `x-transition` / CSS em
  transform+opacity), estados de hover/focus/active claros, feedback imediato.
- Modais e slide-overs para criar/editar; ação destrutiva sempre com modal de
  confirmação. Animação de entrada/saída.
- Toasts para sucesso/erro; nada de alert nativo.
- Busca com debounce; filtros; ordenação; paginação em listas grandes.
- "Cara de sistema": busca/atalhos onde fizer sentido, contadores, badges de
  status, breadcrumbs.

## Estados (sempre tratar)
- Carregando: skeletons ou spinners via `wire:loading` (sem tela travada).
- Vazio: empty state com ilustração/ícone e call-to-action.
- Erro: mensagem clara e recuperável.
- Sucesso: confirmação visual (toast/realce).
- Otimismo de UI onde for seguro (sem comprometer a verdade do dado).

## Responsivo e acessível
- Portal do cliente mobile-first; painel responsivo; alvos de toque adequados.
- Acessibilidade: foco visível, navegação por teclado, ARIA, contraste,
  `prefers-reduced-motion` respeitado.

## Performance (para o efeito não pesar)
- Animar só `transform`/`opacity`; evitar reflow.
- Lazy-load de listas/imagens; paginação; consultas eficientes.
- Efeitos tastefully aplicados — impressionar sem travar nem distrair.

## Nuance profissional (para ficar impecável de verdade)
- Modal é ótimo para ações curtas (criar/editar/confirmar). Em fluxos longos no
  celular (ex.: wizard de agendamento), tela cheia em passos costuma ser melhor
  que modal apertado — decidir por usabilidade, não por regra fixa.
- Efeito serve à clareza, não contra ela: nada de animação que atrase uma tarefa.

## Checklist por tela (Definition of Done de UI)
- [ ] Componentes do design system (sem HTML cru solto)
- [ ] Estados de loading/vazio/erro/sucesso
- [ ] Modais/slide-overs com transição onde aplicável
- [ ] Responsivo + acessível + dark mode
- [ ] Micro-interações de hover/focus e feedback de ação
- [ ] Performance verificada (efeitos em transform/opacity)

## Como está implementado (1E)
- **Tokens** em `resources/css/app.css` (`@layer base`): accent da marca = indigo
  via `--color-accent*` do Flux; `--radius-card`, `--shadow-soft`; tipografia
  Instrument Sans. **Rebrand** = trocar essas variáveis e `npm run build`.
- **Dark mode** dirigido pelo Flux (`@fluxAppearance`), segue o sistema, com
  seletor Claro/Escuro/Sistema no menu de perfil. Layouts não forçam `class="dark"`.
- **Componentes próprios** em `resources/views/components/ng/`: `page-header`,
  `option-card`, `empty`, `skeleton`. O resto usa Flux (button, input, table,
  modal, slide-over/flyout, badge, toast, tabs, avatar, tooltip, dropdown,
  navlist, sidebar, callout).
- **Navegação** com `wire:navigate` (barra de progresso nativa) e estados ativos
  na sidebar via `:current`.

## Portal do cliente — variantes temáticas (Etapa A, 2026-06-21)
O portal reflete a marca do estabelecimento (não o `.dark` do sistema). Por isso os
componentes compartilhados usados ali recebem a prop **`themed`** e classes próprias
em `app.css`: `.ng-card-portal` (cartão de opção/lista), `.ng-skeleton-portal`
(skeleton) e `.ng-fade-in` (transição de conteúdo). Todas usam as CSS vars de
`App\Support\Aparencia` e funcionam com superfície clara OU escura. Ações destrutivas
no portal usam `flux:modal` (não `wire:confirm` nativo). Ver
[[Auditoria de UI (Portal e Painel)]] e [[Identidade Visual do Estabelecimento (Tema)]].

## Painel + dashboard temáticos (Etapa B, 2026-06-21)
O shell do painel e as telas de auth também refletem a marca (`cssVars()`), com a
classe **`.dark`** ligada automaticamente quando a superfície da marca é escura
(`Aparencia::superficieEscura()`) — assim os componentes Flux acompanham. Cards de
superfície da marca usam `.ng-surface` (+ `.ng-surface-muted`, `.ng-divider`,
`.ng-surface-interactive`). O dashboard foi reconstruído sobre essas classes, com
Chart.js nas cores da marca (eixos/tooltip lidos das CSS vars em runtime) e estados
de loading/vazio/erro. Regra de ouro mantida: **dark-safe** — nada de `bg-white`/
`zinc` fixo onde deveria ser `--cor-superficie`. Ver [[Dashboard do Dono]].
