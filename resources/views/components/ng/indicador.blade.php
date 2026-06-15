@props([
    'titulo',
    'valor',
    'sub' => null,
    'icone' => null,
    'tendencia' => null, // float (%) — positivo verde, negativo âmbar; null oculta
])

{{-- Cartão de indicador (KPI) reutilizável do dashboard. --}}
<flux:card class="flex flex-col gap-1">
    <div class="flex items-center justify-between">
        <flux:text class="text-sm text-zinc-500">{{ $titulo }}</flux:text>
        @if ($icone)
            <flux:icon :name="$icone" class="size-4 text-zinc-400" />
        @endif
    </div>

    <div class="text-2xl font-semibold tabular-nums">{{ $valor }}</div>

    <div class="flex items-center gap-2">
        @if (! is_null($tendencia))
            @php($pos = $tendencia >= 0)
            <flux:badge :color="$pos ? 'green' : 'amber'" size="sm" :icon="$pos ? 'arrow-trending-up' : 'arrow-trending-down'">
                {{ $pos ? '+' : '' }}{{ number_format($tendencia, 0) }}%
            </flux:badge>
        @endif
        @if ($sub)
            <flux:text class="text-xs text-zinc-500">{{ $sub }}</flux:text>
        @endif
    </div>
</flux:card>
