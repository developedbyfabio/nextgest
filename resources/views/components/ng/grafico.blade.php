@props([
    'chave',
    'titulo' => null,
    'tipo' => 'bar',
    'dados' => ['labels' => [], 'datasets' => []],
    'altura' => 'h-64',
    'legenda' => null,
    'vazio' => false,
])

{{--
    Cartão de gráfico reutilizável (Etapa 4). Renderiza um <canvas> controlado
    pelo Alpine `ngGrafico` (resources/js/app.js). O wrapper usa wire:ignore para
    o Livewire não destruir o Chart a cada re-render; a atualização ao vivo vem
    do evento `metricas-atualizadas`. `chave` casa o gráfico com a fatia de dados.
--}}
<flux:card class="flex flex-col gap-3">
    @if ($titulo)
        <flux:heading size="sm">{{ $titulo }}</flux:heading>
    @endif

    @if ($vazio)
        <div class="{{ $altura }} flex items-center justify-center">
            <x-ng.empty icon="chart-bar" title="Sem dados no período" />
        </div>
    @else
        <div wire:ignore class="{{ $altura }}"
            x-data="ngGrafico({ chave: @js($chave), tipo: @js($tipo), dados: @js($dados), legenda: @js($legenda) })">
            <canvas x-ref="canvas"></canvas>
        </div>
    @endif
</flux:card>
