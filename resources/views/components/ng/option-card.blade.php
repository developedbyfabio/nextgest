@props(['selected' => false])

{{-- Cartão selecionável/clicável do design system (opções, itens de lista). --}}
<button
    type="button"
    aria-pressed="{{ $selected ? 'true' : 'false' }}"
    {{ $attributes->class([
        'ng-card-interactive flex items-center justify-between gap-3 p-3',
        'ng-card-selected' => $selected,
    ])->merge(['style' => $selected ? 'background-color: color-mix(in srgb, var(--color-accent) 8%, transparent);' : null]) }}
>
    <span class="min-w-0 flex-1">{{ $slot }}</span>

    @if ($selected)
        <flux:icon name="check-circle" variant="solid" class="size-5 shrink-0" style="color: var(--color-accent);" />
    @endif
</button>
