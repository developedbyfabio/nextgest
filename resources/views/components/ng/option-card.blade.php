@props(['selected' => false, 'themed' => false])

{{-- Cartão selecionável/clicável do design system (opções, itens de lista).
     `themed` (portal): segue a identidade do estabelecimento (CSS vars). Sem
     `themed` (painel/admin): superfícies neutras do Flux (zinc/dark). --}}
<button
    type="button"
    aria-pressed="{{ $selected ? 'true' : 'false' }}"
    @if ($themed)
        {{ $attributes->class('ng-card-portal flex items-center justify-between gap-3 p-4') }}
    @else
        {{ $attributes->class([
            'ng-card-interactive flex items-center justify-between gap-3 p-3',
            'ng-card-selected' => $selected,
        ])->merge(['style' => $selected ? 'background-color: color-mix(in srgb, var(--color-accent) 8%, transparent);' : null]) }}
    @endif
>
    <span class="min-w-0 flex-1">{{ $slot }}</span>

    @if ($selected)
        <flux:icon name="check-circle" variant="solid" class="size-5 shrink-0" style="color: var(--color-accent);" />
    @endif
</button>
