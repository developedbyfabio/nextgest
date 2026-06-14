@props(['title', 'subtitle' => null])

{{-- Cabeçalho padrão de página do painel: título + subtítulo + ações. --}}
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <flux:heading size="xl">{{ $title }}</flux:heading>
        @if ($subtitle)
            <flux:subheading>{{ $subtitle }}</flux:subheading>
        @endif
    </div>

    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
