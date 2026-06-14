# 12 — Design System (fatia 1E)

Padroniza a UI das fatias 1A–1D: tokens de marca, dark mode consistente,
componentes compartilhados e estados (loading/vazio/erro/sucesso). Sem mudar
regra de negócio nem schema.

## Tokens e tema (`resources/css/app.css`)

- **Cor da marca**: indigo, via variáveis Flux `--color-accent*` em `@layer base`
  (`:root` para claro, `.dark` para escuro). **Para rebrandar**, troque só esses
  valores (ex.: `var(--color-emerald-600)`); botões primários, foco, links e
  badges accent acompanham.
- **Tipografia**: `--font-sans` = Instrument Sans (carregada via Vite/Bunny).
- **Tokens extras**: `--radius-card`, `--shadow-soft`.
- **Dark mode**: dirigido pelo Flux (`@fluxAppearance`) — segue a preferência do
  usuário/sistema. Há seletor **Claro/Escuro/Sistema** no menu de perfil do
  painel e do admin. Os layouts não forçam mais `class="dark"`.
- **Acessibilidade**: `prefers-reduced-motion` desativa animações; foco visível
  com `focus-visible:ring`. Animações usam só `transform`/`opacity`.

## Utilitários (`@layer components`)

- `.ng-card-interactive` — cartão clicável (borda, hover sutil em `-translate-y`,
  ring de foco). `.ng-card-selected` — estado selecionado (borda/anel accent).
- `.ng-skeleton` — bloco de carregamento (`animate-pulse`).

## Componentes Blade compartilhados (`resources/views/components/ng/`)

| Componente | Uso |
|---|---|
| `<x-ng.page-header title subtitle>` + slot `actions` | Cabeçalho padrão das páginas do painel |
| `<x-ng.option-card :selected wire:click>` | Cartão selecionável (serviços, profissionais, clientes, unidades) |
| `<x-ng.empty icon title text>` | Estado vazio |
| `<x-ng.skeleton :rows :height>` | Lista de skeletons para `wire:loading` |

O restante da UI usa os componentes do **Flux** (button, input, field, select,
table, modal, badge, toast, tabs, avatar, tooltip, dropdown, navlist, sidebar,
callout, separator, card) — sem HTML cru solto nas telas.

## Aplicação por tela

- **Auth** (admin/equipe/cliente): layout **split** — painel de marca (gradiente)
  à esquerda, formulário à direita; validação inline (Flux) e feedback de
  throttle (mensagem no campo).
- **Painel**: sidebar Flux com `wire:navigate` e estado ativo; cabeçalhos via
  `x-ng.page-header`; CRUDs em modal com transição; toasts; `wire:loading` com
  skeleton na agenda.
- **Portal** (mobile-first): coluna estreita; wizard de agendamento em passos com
  **indicador de progresso**, cards selecionáveis, skeleton de horários, callout
  de confirmação e mensagem amigável de slot indisponível.
- **Agenda da equipe**: itens com `ng-card-interactive`, badges de status, chips
  na visão semana, slide-over de detalhe, modal de novo agendamento.

## Navegação SPA

`wire:navigate` no painel e portal (a barra de progresso de navegação é nativa do
Livewire). Estados ativos via `:current` no `flux:navlist`.

## Checklist (Definition of Done) por tela

- [x] Componentes do design system (Flux + `x-ng.*`), sem HTML cru solto
- [x] Estados: loading (skeleton/`wire:loading`), vazio (`x-ng.empty`), erro
      (validação inline/toast), sucesso (toast)
- [x] Modais/slide-overs com transição (Flux)
- [x] Responsivo + dark mode + acessível (foco, ARIA, `prefers-reduced-motion`)
- [x] Micro-interações de hover/focus; animação só em transform/opacity
- [x] Testes 1A–1D verdes (74)

## Como trocar a marca

1. Edite `--color-accent*` (claro e escuro) em `resources/css/app.css`.
2. `npm run build`.
3. (Opcional) ajuste o gradiente do painel de marca em
   `resources/views/components/layouts/auth.blade.php`.
