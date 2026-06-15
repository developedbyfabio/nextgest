import Chart from 'chart.js/auto';

/*
| Gráficos do dashboard (Etapa 4).
|
| Componente Alpine reutilizável `ngGrafico`: cria um Chart.js a partir da
| configuração passada pelo Blade (x-ng.grafico) e atualiza ao vivo quando o
| dashboard despacha `metricas-atualizadas` (troca de filtro), sem recriar o
| canvas (o wrapper usa wire:ignore). As cores vêm do tema do estabelecimento,
| resolvidas no servidor (Aparencia) e embutidas nos datasets.
*/
document.addEventListener('alpine:init', () => {
    window.Alpine.data('ngGrafico', (config) => ({
        chart: null,

        init() {
            this.chart = new Chart(this.$refs.canvas, {
                type: config.tipo,
                data: config.dados,
                options: opcoes(config),
            });

            // Atualiza este gráfico quando o dashboard recalcula as métricas.
            const handler = (evento) => {
                const dados = (evento?.graficos ?? evento?.[0]?.graficos)?.[config.chave];
                if (!dados) return;
                this.chart.data.labels = dados.labels;
                this.chart.data.datasets = dados.datasets;
                this.chart.update();
            };

            window.Livewire?.on('metricas-atualizadas', handler);

            // Limpa o Chart ao sair da página (navegação SPA do Livewire).
            this.$el.addEventListener('livewire:navigating', () => this.chart?.destroy(), { once: true });
        },
    }));
});

function opcoes(config) {
    const categorico = config.tipo === 'doughnut' || config.tipo === 'pie';

    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: config.legenda ?? categorico,
                position: 'bottom',
                labels: { boxWidth: 12, usePointStyle: true },
            },
            tooltip: { enabled: true },
        },
        scales: categorico
            ? {}
            : {
                  x: { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0 } },
                  y: { beginAtZero: true, ticks: { precision: 0 } },
              },
    };
}
