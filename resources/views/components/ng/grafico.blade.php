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
    Cartão de gráfico do dashboard. Superfície da marca (dark-safe). Renderiza um
    <canvas> controlado pelo Alpine `ngGrafico` (resources/js/app.js). O wrapper usa
    wire:ignore para o Livewire não destruir o Chart a cada re-render; a atualização
    ao vivo vem do evento `metricas-atualizadas`. `chave` casa o gráfico com a fatia
    de dados. As cores de eixos/tooltip são lidas das CSS vars da marca em runtime.
--}}
<div class="ng-surface flex flex-col gap-3 p-5">
    @if ($titulo)
        <flux:heading size="sm" style="color: var(--cor-texto);">{{ $titulo }}</flux:heading>
    @endif

    @if ($vazio)
        <div class="{{ $altura }} flex items-center justify-center">
            <x-ng.empty themed icon="chart-bar" title="Sem dados no período" text="Ajuste o período ou registre movimento." />
        </div>
    @else
        <div wire:ignore class="{{ $altura }}"
            x-data="ngGrafico({ chave: @js($chave), tipo: @js($tipo), dados: @js($dados), legenda: @js($legenda) })">
            <canvas x-ref="canvas"></canvas>
        </div>
    @endif
</div>
