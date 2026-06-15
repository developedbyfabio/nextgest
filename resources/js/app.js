import Chart from 'chart.js/auto';
import Sortable from 'sortablejs';

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
    /*
    | Kanban (Etapa 5): arrastar-e-soltar entre colunas e reordenar.
    | Cada lista de cartões de uma coluna é um Sortable do grupo 'kanban'. No
    | drop, chama Livewire `moverCartao(id, colunaDestino, novaOrdem)` —
    | atualização otimista (o DOM já moveu; o servidor persiste; última escrita
    | vence). A alternativa por teclado é o menu "Mover para" em cada cartão.
    */
    window.Alpine.data('kanbanColuna', () => ({
        init() {
            Sortable.create(this.$el, {
                group: 'kanban',
                animation: 150,
                draggable: '[data-cartao-id]',
                ghostClass: 'opacity-40',
                onEnd: (evt) => {
                    const cartaoId = evt.item.dataset.cartaoId;
                    const colunaDestino = evt.to.dataset.colunaId;
                    this.$wire.moverCartao(cartaoId, colunaDestino, evt.newIndex);
                },
            });
        },
    }));

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
