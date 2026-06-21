@props([
    'nome' => '',
    'logoUrl' => null,
    'href' => null, // quando há link (portal real); nulo na prévia
])

{{-- Cabeçalho do portal do cliente (logo + nome + ações à direita via slot).
     FONTE DE VERDADE única: usado pelo layout do portal real e pela prévia. --}}
<header class="sticky top-0 z-10 flex items-center justify-between border-b px-4 py-3 backdrop-blur"
    style="background-color: color-mix(in srgb, var(--cor-superficie) 90%, transparent); border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent);">
    @if ($href)
        <a href="{{ $href }}" class="flex min-w-0 items-center gap-2" wire:navigate>
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $nome }}" class="size-8 rounded object-contain" />
            @else
                <flux:icon name="calendar-days" class="size-6 shrink-0" style="color: var(--cor-principal);" />
            @endif
            <span class="truncate text-base font-semibold">{{ $nome }}</span>
        </a>
    @else
        <div class="flex min-w-0 items-center gap-2">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $nome }}" class="size-8 rounded object-contain" />
            @else
                <flux:icon name="calendar-days" class="size-6 shrink-0" style="color: var(--cor-principal);" />
            @endif
            <span class="truncate text-base font-semibold">{{ $nome }}</span>
        </div>
    @endif

    <div class="flex shrink-0 items-center gap-1">{{ $slot }}</div>
</header>
