---
projeto: Nextgest
tipo: bug-e-correcao
modulo: ui-livewire
status: resolvido
criado: 2026-06-22
tags: [nextgest, bug, livewire, flux, wire-loading, skeleton, ui]
---

# Bug — skeleton de loading que NUNCA some (painel/portal)

> Projeto: [[Nextgest - Visão Geral]] · Resolvido · ver
> [[Gotchas e Aprendizados do Projeto]] e [[Padrao de UI-UX (Design System)]]

## Problema
Em todas as telas de lista do painel (**agenda, serviços, produtos, comandas, kanban**)
aparecia um **retângulo de skeleton no topo que nunca sumia** — os dados reais
renderizavam logo abaixo. No **kanban**, os skeletons das colunas ficavam **empilhados
sobre** as colunas reais. (Reportado no navegador pelo Fabio.)

## Causa raiz (confirmada no código do Livewire 4)
O skeleton é um elemento **`wire:loading`** ("mostrar durante o loading"). No
**Livewire 4**, o diretivo `wire:loading` (em `vendor/.../dist/livewire.js`,
`directive("loading")`) **só alterna o `display` inline em resposta a uma requisição**
(mostra no `onSend`, esconde no `onSuccess/onFinish`). Ele **NÃO esconde o elemento na
inicialização**.

> [!danger] Diferença vs Livewire 2/3
> Versões antigas injetavam uma folha de estilo `[wire\:loading]{display:none}` que
> escondia tudo por padrão. O **Livewire 4 não injeta isso** (confirmado: os únicos
> `<style>` injetados são do **nprogress** e das **view-transitions**; não há regra de
> `wire:loading` em CSS nem no vendor nem no nosso build).

Logo, no **primeiro render** (sem requisição em curso) o skeleton — um `<div>`
(`display:block`) — fica **visível para sempre**, só sumindo depois de disparar uma das
ações alvo. Os blocos de **dados** usam `wire:loading.remove` (aparecem em repouso,
somem no loading) — por isso "os dados apareciam" normalmente.

Detalhe que mascarava: o **nome do atributo inclui os modificadores**
(`wire:loading.delay.flex`), então um seletor `[wire\:loading]` **não casa** com ele —
não há um seletor único que cubra todas as variantes.

## Correção (na raiz, uma vez — `resources/css/app.css`)
Regra CSS (fora de `@layer`, p/ vencer utilitários) que **começa escondido** cada
variante "mostrar-no-loading" usada:

```css
[wire\:loading],
[wire\:loading\.delay],
[wire\:loading\.delay\.flex],
[wire\:loading\.flex\.delay] { display: none; }
```

- Em **repouso**: `display:none` (esconde skeleton/spinner).
- **Durante** o loading: o Livewire seta `display` **inline** (`flex`/`inline-block`),
  que **vence** a regra → aparece.
- Ao **terminar**: o Livewire seta `display:none` inline → some.
- `.remove` **não** entra (deve aparecer em repouso). Vale claro e escuro (sem cor).

Corrigiu de uma vez **todas** as telas (painel e também portal/onboarding: skeleton de
horários do wizard e os spinners de botão "Confirmando…/Criando…/Cancelando…", que
sofriam do mesmo problema).

## Como testar / evitar no futuro
- **Teste de regressão:** `tests/Feature/Painel/WireLoadingSkeletonTest.php` varre as
  views e exige que **cada variante `wire:loading` "mostrar"** tenha um seletor de
  esconder no `app.css` (e que `.remove` **não** seja escondido). Pega o estado de
  **repouso** (a lição do "teste verde × navegador": o HTML do servidor sempre contém o
  markup do skeleton — o que importa é o `display` em repouso).
- **Ao criar um novo `wire:loading` "mostrar" com um novo combo de modificadores**
  (ex.: `wire:loading.grid`), **adicione o seletor** no bloco do `app.css` — o teste
  falha até cobrir.
