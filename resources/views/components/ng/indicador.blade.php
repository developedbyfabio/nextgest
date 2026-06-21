@props([
    'titulo',
    'valor',
    'sub' => null,
    'icone' => null,
    'tendencia' => null, // float (%) — positivo verde, negativo âmbar; null oculta
])

{{-- Cartão de indicador (KPI) do dashboard. Superfície da marca (dark-safe). --}}
<div class="ng-surface ng-surface-interactive flex flex-col gap-3 p-5">
    <div class="flex items-start justify-between gap-2">
        <flux:text class="text-sm font-medium" style="color: var(--cor-texto-suave);">{{ $titulo }}</flux:text>
        @if ($icone)
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl" style="background-color: color-mix(in srgb, var(--cor-principal) 14%, transparent); color: var(--cor-principal);">
                <flux:icon :name="$icone" class="size-5" />
            </span>
        @endif
    </div>

    <div class="text-3xl font-bold tracking-tight tabular-nums" style="color: var(--cor-texto);">{{ $valor }}</div>

    @if (! is_null($tendencia) || $sub)
        <div class="flex flex-wrap items-center gap-2">
            @if (! is_null($tendencia))
                @php($pos = $tendencia >= 0)
                <flux:badge :color="$pos ? 'green' : 'amber'" size="sm" :icon="$pos ? 'arrow-trending-up' : 'arrow-trending-down'">
                    {{ $pos ? '+' : '' }}{{ number_format($tendencia, 0) }}%
                </flux:badge>
            @endif
            @if ($sub)
                <flux:text class="text-xs" style="color: var(--cor-texto-suave);">{{ $sub }}</flux:text>
            @endif
        </div>
    @endif
</div>
