@props(['icon' => 'inbox', 'title' => 'Nada por aqui', 'text' => null, 'themed' => false])

{{-- Estado vazio padrão. `themed` (portal): bordas/ícone/texto seguem a
     identidade do estabelecimento (CSS vars). Sem `themed`: neutro (zinc/dark). --}}
@if ($themed)
    <div {{ $attributes->class('flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-12 text-center')->merge(['style' => 'border-color: color-mix(in srgb, var(--cor-texto) 15%, transparent);']) }}>
        <flux:icon :name="$icon" class="size-8" style="color: color-mix(in srgb, var(--cor-texto) 35%, transparent);" />
        <flux:heading size="sm" style="color: var(--cor-texto);">{{ $title }}</flux:heading>
        @if ($text)
            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">{{ $text }}</flux:text>
        @endif
        {{ $slot }}
    </div>
@else
    <div {{ $attributes->class('flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-200 px-6 py-12 text-center dark:border-zinc-700') }}>
        <flux:icon :name="$icon" class="size-8 text-zinc-300 dark:text-zinc-600" />
        <flux:heading size="sm">{{ $title }}</flux:heading>
        @if ($text)
            <flux:text class="text-sm text-zinc-500">{{ $text }}</flux:text>
        @endif
        {{ $slot }}
    </div>
@endif
