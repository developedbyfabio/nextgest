@props([
    'nome' => '',
    'logoUrl' => null,
    'aparencia' => [], // aparência do tenant (define a marca — ver x-portal.marca)
    'href' => null, // quando há link (portal real); nulo na prévia
])

{{-- Cabeçalho do portal do cliente (logo + nome + ações à direita via slot).
     FONTE DE VERDADE única: usado pelo layout do portal real e pela prévia. --}}
<header class="sticky top-0 z-10 flex items-center justify-between border-b px-4 py-3 backdrop-blur"
    style="background-color: color-mix(in srgb, var(--cor-superficie) 90%, transparent); border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent);">
    @if ($href)
        <a href="{{ $href }}" class="flex min-w-0 items-center gap-2" wire:navigate>
            <x-portal.marca :aparencia="$aparencia" :logo-url="$logoUrl" :nome="$nome" contexto="topo" />
            <span class="truncate text-base font-semibold">{{ $nome }}</span>
        </a>
    @else
        <div class="flex min-w-0 items-center gap-2">
            <x-portal.marca :aparencia="$aparencia" :logo-url="$logoUrl" :nome="$nome" contexto="topo" />
            <span class="truncate text-base font-semibold">{{ $nome }}</span>
        </div>
    @endif

    <div class="flex shrink-0 items-center gap-1">{{ $slot }}</div>
</header>
