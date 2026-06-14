@props(['selected' => false])

{{-- Cartão selecionável/clicável do design system (opções, itens de lista). --}}
<button
    type="button"
    aria-pressed="{{ $selected ? 'true' : 'false' }}"
    {{ $attributes->class([
        'ng-card-interactive flex items-center justify-between gap-3 p-3',
        'ng-card-selected bg-indigo-50/60 dark:bg-indigo-950/20' => $selected,
    ]) }}
>
    <span class="min-w-0 flex-1">{{ $slot }}</span>

    @if ($selected)
        <flux:icon name="check-circle" variant="solid" class="size-5 shrink-0 text-indigo-600 dark:text-indigo-400" />
    @endif
</button>
