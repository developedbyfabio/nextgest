@props([
    'funcionamento' => [],
    'prefix' => 'funcionamento', // caminho da propriedade Livewire no componente pai
])

{{-- Editor do horário semanal de funcionamento (toggles por dia + início/fim).
     FONTE DE VERDADE única: usado no onboarding (admin) e na tela do painel do dono.
     Vincula via wire:model a uma propriedade-array do componente PAI (`$prefix`). --}}
<div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-700">
    @foreach ($funcionamento as $i => $f)
        <div class="flex flex-wrap items-center gap-4 py-3">
            <div class="w-28">
                <flux:switch wire:model.live="{{ $prefix }}.{{ $i }}.aberto" label="{{ $f['rotulo'] }}" />
            </div>
            @if ($f['aberto'])
                <div class="flex items-center gap-2">
                    <flux:input type="time" wire:model="{{ $prefix }}.{{ $i }}.inicio" class="w-32" />
                    <span class="text-zinc-400">até</span>
                    <flux:input type="time" wire:model="{{ $prefix }}.{{ $i }}.fim" class="w-32" />
                </div>
            @else
                <flux:text class="text-sm text-zinc-400">Fechado</flux:text>
            @endif
            <flux:error name="{{ $prefix }}.{{ $i }}.fim" />
        </div>
    @endforeach
</div>
