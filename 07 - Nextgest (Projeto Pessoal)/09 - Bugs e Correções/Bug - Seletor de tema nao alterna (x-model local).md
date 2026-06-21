---
projeto: Nextgest
tipo: bug-e-correcao
modulo: tema-flux
status: resolvido
criado: 2026-06-21
tags: [nextgest, bug, tema, flux, dark-mode, alpine, livewire, d36]
---

# Bug — seletor Claro/Escuro/Sistema não alterna (x-model em cópia local)

> Projeto: [[Nextgest - Visão Geral]] · Resolvido · ver
> [[Identidade Visual do Estabelecimento (Tema)]] e [[Decisões de Arquitetura]] (D36)

## Sintoma (navegador, reportado pelo Fabio)
No portal e no painel, clicar em **Claro / Escuro / Sistema** **não mudava** o tema —
ficava tudo no escuro independentemente da escolha. A troca deveria ligar/desligar a
classe `.dark` no `<html>`, refletir na hora e **persistir**.

## Auditoria da cadeia (o que estava certo)
- `@fluxAppearance` **presente e único** no `<head>` de cada layout (portal/painel/auth),
  rodando antes da pintura (anti-flash). Ele define `window.Flux.applyAppearance()` e, no
  load, chama `applyAppearance(localStorage['flux.appearance'] || 'system')`.
- `@fluxScripts` carrega `/flux/flux.js` (200). O Flux registra o magic Alpine `$flux`
  (objeto reativo global, `window.Flux`) e — crucial — **`Alpine.effect(() => applyAppearance($flux.appearance))`**: sempre que `$flux.appearance` muda, ele aplica `.dark`
  e persiste.
- **Nenhum** resíduo da Etapa B forçando tema: sem `.dark` fixo no `<html>` do servidor,
  sem `superficieEscura()`, sem `data-flux-appearance`, sem luminância. O acento da marca
  é emitido server-side e **não** mexe em `.dark`.

## Causa raiz (onde quebrava)
O seletor ligava o `x-model` a uma **cópia local**:

```blade
{{-- ERRADO --}}
<flux:menu.radio.group x-data="{ appearance: $flux.appearance }" x-model="appearance">
```

`x-data="{ appearance: $flux.appearance }"` só **lê o valor inicial** de `$flux.appearance`
para uma variável local; os rádios passam a escrever **nessa variável local**, nunca em
`$flux.appearance`. Como o `Alpine.effect` do Flux observa **`$flux.appearance`** (não a
cópia), ele **nunca dispara** → `applyAppearance` não roda → `.dark` não muda e nada é
gravado no `localStorage`. Resultado: como o default é `system` e o SO do Fabio está no
escuro, abre escuro e fica preso. Estava assim nos **três** lugares: `x-ng.seletor-tema`
(portal/painel) e os menus de perfil de `layouts/painel` e `layouts/admin`.

## Correção (padrão oficial do Flux)
Ligar o `x-model` **direto** no objeto reativo `$flux.appearance` (com `x-data` vazio só
para criar escopo Alpine):

```blade
{{-- CERTO --}}
<flux:menu.radio.group x-data x-model="$flux.appearance">
```

Agora selecionar muta `$flux.appearance` → o effect do Flux roda → `.dark` é
adicionado/removido no `<html>`, o valor é gravado em `localStorage` (`light`/`dark`) ou
removido (`system`), e no reload o `@fluxAppearance` reaplica. "Sistema" segue
`prefers-color-scheme` e reage à troca do SO (listener `matchMedia` do Flux).

## Verificação
- **Navegador / cadeia real:** HTML servido do portal traz `x-model="$flux.appearance"`,
  **sem** o padrão quebrado `x-model="appearance"`, com `@fluxAppearance` uma única vez e
  `<html lang="pt-BR">` sem `.dark` forçado.
- **Testes** (`tests/Feature/Tema/SeletorTemaTest.php`): o seletor (portal e painel) liga
  em `$flux.appearance` e **não** na cópia local; `@fluxAppearance` aparece 1× e inicializa
  do `localStorage`/sistema; o servidor não força `.dark`. Suíte: **229 verde**.

## Lição
- "Teste verde × navegador": os testes antigos só viam que o seletor **existia** (rótulos
  Claro/Escuro/Sistema). Não pegavam o **mecanismo** (binding inerte). Testar o efeito:
  qual variável o `x-model` realmente muta.
- Com Flux, **bind direto no magic** (`x-model="$flux.appearance"`); criar uma cópia local
  de um magic reativo quebra silenciosamente a reatividade.
