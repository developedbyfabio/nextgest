---
projeto: Nextgest
tipo: bug-e-correcao
modulo: ui-flux
status: resolvido
criado: 2026-06-21
tags: [nextgest, bug, flux, livewire, blade]
---

# Bug — `@disabled` em componente Flux quebra a compilação Blade

> Projeto: [[Nextgest - Visão Geral]] · Resolvido · ver
> [[Padrao de UI-UX (Design System)]] e [[Gotchas e Aprendizados do Projeto]]

## Problema
Usar a diretiva Blade `@disabled(...)` **dentro de um componente Flux**
(`<flux:button>`, `<flux:menu.item>`…) quebra a compilação/renderização da view.

## Causa
A diretiva `@disabled(cond)` é açúcar do Blade para elementos **HTML nativos** — emite
o atributo bruto `disabled="disabled"`. Componentes Flux processam seus atributos pela
própria classe do componente (binding Livewire/Alpine); o atributo cru injetado pela
diretiva não casa com esse processamento e estoura na compilação.

## Correção
Em componente Flux, usar **binding de atributo** `:disabled="cond"`:

```blade
{{-- ERRADO em componente Flux --}}
<flux:button wire:click="confirmar" @disabled($slotHora === null)>Confirmar</flux:button>

{{-- CERTO --}}
<flux:button wire:click="confirmar" :disabled="$slotHora === null">Confirmar</flux:button>
```

> [!note] `@disabled` ainda vale em HTML puro
> Em `<button>`/`<input>` nativos, `@disabled(cond)` funciona normalmente (ex.:
> `resources/views/livewire/admin/onboarding-estabelecimento.blade.php` o usa num
> `<button>` de navegação do wizard). A regra é: **Flux → `:disabled`; HTML nativo →
> `@disabled` (ou `:disabled`)**.

## Como testar / evitar no futuro
- `php artisan view:clear` e abrir a tela; a página renderiza sem erro de compilação.
- Convenção do projeto: em qualquer `<flux:*>`, atributos condicionais (`disabled`,
  `readonly`…) sempre por **binding `:atributo="cond"`**, nunca via diretiva `@atributo`.
- Padrão já seguido no código: `:disabled` nos `<flux:button>`/`<flux:menu.item>` da
  agenda, do portal, do kanban e do onboarding.
