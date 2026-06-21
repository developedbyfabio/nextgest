@props([
    'nome' => '',
    'descricao' => null,
    'headerUrl' => null,
    'registrarHref' => null, // portal real: links; prévia: nulo (botões estáticos)
    'loginHref' => null,
])

{{-- Tela INÍCIO (visitante): capa + como funciona + chamadas. FONTE DE VERDADE
     única: home do portal real (visitante) e tela 1 do carrossel da prévia. --}}
<div class="flex flex-1 flex-col gap-5">
    <x-portal.capa :nome="$nome" :descricao="$descricao" :header-url="$headerUrl" />

    <x-portal.como-funciona />

    <div class="mt-auto flex flex-col gap-2 pt-2">
        @if ($registrarHref)
            <flux:button :href="$registrarHref" variant="primary" icon="calendar-days" class="w-full" wire:navigate>
                Criar conta e agendar
            </flux:button>
            <flux:button :href="$loginHref" variant="ghost" class="ng-leitura w-full" wire:navigate>
                Já tenho conta
            </flux:button>
        @else
            <button type="button" class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">
                Criar conta e agendar
            </button>
            <button type="button" class="ng-leitura w-full rounded-lg border px-4 py-2.5 text-sm font-medium" style="color: var(--cor-texto); border-color: color-mix(in srgb, var(--cor-texto) 12%, transparent);">
                Já tenho conta
            </button>
        @endif
    </div>
</div>
