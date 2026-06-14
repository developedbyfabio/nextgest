@props(['icon' => 'inbox', 'title' => 'Nada por aqui', 'text' => null])

{{-- Estado vazio padrão. --}}
<div {{ $attributes->class('flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-200 px-6 py-12 text-center dark:border-zinc-700') }}>
    <flux:icon :name="$icon" class="size-8 text-zinc-300 dark:text-zinc-600" />
    <flux:heading size="sm">{{ $title }}</flux:heading>
    @if ($text)
        <flux:text class="text-sm text-zinc-500">{{ $text }}</flux:text>
    @endif
    {{ $slot }}
</div>
