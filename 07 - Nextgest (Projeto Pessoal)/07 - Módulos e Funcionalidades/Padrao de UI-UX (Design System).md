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

### Confirmação padrão: `x-ng.confirmar` (nunca `confirm()` nativo) — D27/D65
Toda confirmação (painel do tenant E /admin) usa o componente **`x-ng.confirmar`**
(`components/ng/confirmar.blade.php`): modal Flux com `titulo`/`texto`/`icone`/`tom`
(**red** destrutiva | **amber** atenção/reversível), "Voltar" + o botão de confirmar no
**slot**. **Proibido** `wire:confirm`/`window.confirm` (popup "site diz…"). Disparo:
`wire:click="pedirX(...)"` → `Flux::modal('nome')->show()`; o confirmar chama o método da
ação e fecha o modal. O /admin foi alinhado a esse padrão no **D65** (havia 4 `wire:confirm`).

### Animação do menu lateral (D66)
Abrir um grupo do acordeão da sidebar tem transição suave (antes "teleportava"): keyframe
`ng-menu-reveal` (fade + slide curto) em `ui-disclosure[data-flux-sidebar-group] > div` (`app.css`),
com `prefers-reduced-motion` respeitado. **Só visual** — não toca a lógica do acordeão nem o highlight
do grupo/item ativo (D47/D52); roda ao abrir (display→block) e no load; sobrevive a `wire:navigate`.

## Painel + dashboard temáticos (Etapa B, 2026-06-21)
O shell do painel e as telas de auth também refletem a marca (`cssVars()`), com a
classe **`.dark`** ligada automaticamente quando a superfície da marca é escura
(`Aparencia::superficieEscura()`) — assim os componentes Flux acompanham. Cards de
superfície da marca usam `.ng-surface` (+ `.ng-surface-muted`, `.ng-divider`,
`.ng-surface-interactive`). O dashboard foi reconstruído sobre essas classes, com
Chart.js nas cores da marca (eixos/tooltip lidos das CSS vars em runtime) e estados
de loading/vazio/erro. Regra de ouro mantida: **dark-safe** — nada de `bg-white`/
`zinc` fixo onde deveria ser `--cor-superficie`. Ver [[Dashboard do Dono]].

## Modo claro / escuro / sistema (Etapa D, 2026-06-21) — substitui parte de A/B
> [!important] Correção do modelo
> As notas das Etapas A/B abaixo falam em "superfície da marca" e "dark-safe via
> `.dark` por luminância". Isso foi **substituído** (D36): a **marca é só o acento +
> logo + tipografia**; as **superfícies seguem o modo claro/escuro/sistema** do Flux.
- Tokens de superfície (`--cor-fundo/superficie/texto/texto-suave`) vivem em `app.css`
  (`:root` claro / `.dark` escuro). As classes `.ng-*` e as views seguem o modo sem
  alteração. Superfície e texto trocam **juntos** (evita o bug da Etapa A).
- `@fluxAppearance` em todos os layouts (portal/painel/auth). Seletor
  Claro/Escuro/Sistema: header do portal (`x-ng.seletor-tema`) e menu de perfil do
  painel. Persistência por `localStorage` (Flux). Ver [[Decisões de Arquitetura]] D36.

## Cadastros elevados (Polimento 2, 2026-06-22)
As seis telas de cadastro (unidades, serviços, equipe, horários, papéis, bloqueios)
no mesmo padrão: tokens da Etapa D (sem zinc fixo), **estado vazio temático**
(`x-ng.empty themed` com CTA, fora da tabela), **confirmação por `flux:modal`**
(componente reutilizável **`x-ng.confirmar`**, sem `confirm` nativo em nenhuma),
inativar = não apaga. **Busca + skeleton** em serviços e equipe (listas que crescem);
serviços mostra a **% de comissão** (2C). Regras/dados de agendamento e permissões
spatie **intactos** — só apresentação. **Priorização:** papéis já estava consistente
(deixei como estava; rótulos amigáveis de permissão ficam como micro-polimento
futuro); unidades/papéis/bloqueios sem busca por terem poucas linhas.

## Agenda elevada (Polimento 1, 2026-06-22)
Tela de maior uso diária, levada ao padrão das demais (tokens da Etapa D, sem zinc
fixo): cartões em `.ng-surface`/`.ng-surface-interactive` com **barra de acento por
status** (cor semântica, não a marca); visão **dia** (lista) e **semana** (grade no
desktop, **scroll horizontal com snap** no mobile, como o kanban); **modal de detalhe**
(flyout) com ações — cancelar por `flux:modal` (sem `confirm` nativo) e **"gerar
comanda"** no concluído (Fatia 2B); estados **loading (skeleton) / dia vazio / erro
recuperável**. **As regras de agenda não foram tocadas** — `MotorDisponibilidade` e
`Agendador` (disponibilidade/concorrência/lock) seguem intactos; a elevação é só
apresentação. Ver [[Auditoria de UI (Portal e Painel)]].

## Kanban temático + DnD elevado (Etapa C, 2026-06-21)
Colunas (`.ng-surface-muted`) e cartões (`.ng-surface`) seguem a marca; dark-safe.
DnD (SortableJS) com **handle**, placeholder (`.ng-kanban-ghost`) e elevação
(`.ng-kanban-drag`); update otimista com **revert + toast** em falha (board↔banco
consistentes). Confirmações em `flux:modal` (sem `confirm` nativo); "excluir" cartão =
**arquivar** (soft delete). Estados de skeleton/vazio/erro e responsivo com snap entre
colunas no celular. Ver [[Kanban (Atendimento e CRM)]].
