import Chart from 'chart.js/auto';
import Sortable from 'sortablejs';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

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
    | Kanban (Etapa 5/C): arrastar-e-soltar entre colunas e reordenar.
    | Cada lista de cartões de uma coluna é um Sortable do grupo 'kanban', arrastado
    | pelo HANDLE ([data-kanban-handle]). No drop, chama Livewire
    | `moverCartao(id, colunaDestino, novaOrdem)` — atualização OTIMISTA (o DOM já
    | moveu; o servidor persiste coluna+ordem). Se o servidor RECUSAR (ex.: 403),
    | revertemos o cartão para a posição original e avisamos (toast), para o board e o
    | banco nunca divergirem. Alternativa acessível: menu "Mover para" em cada cartão.
    */
    window.Alpine.data('kanbanColuna', () => ({
        init() {
            Sortable.create(this.$el, {
                group: 'kanban',
                animation: 150,
                draggable: '[data-cartao-id]',
                handle: '[data-kanban-handle]',
                ghostClass: 'ng-kanban-ghost',
                chosenClass: 'ng-kanban-chosen',
                dragClass: 'ng-kanban-drag',
                forceFallback: true, // estilo de arraste consistente entre navegadores
                fallbackOnBody: true,
                onEnd: (evt) => {
                    const item = evt.item;
                    const cartaoId = item.dataset.cartaoId;
                    const colunaDestino = evt.to.dataset.colunaId;

                    const resultado = this.$wire.moverCartao(cartaoId, colunaDestino, evt.newIndex);

                    // Revert em falha: devolve o cartão à coluna/posição de origem.
                    Promise.resolve(resultado).catch(() => {
                        const origem = evt.from;
                        const ref = origem.children[evt.oldIndex] || null;
                        origem.insertBefore(item, ref);
                        window.Flux?.toast?.({ text: 'Não foi possível mover o cartão. Tente novamente.', variant: 'danger' });
                    });
                },
            });
        },
    }));

    /*
    | Foto de perfil (Item 5): recorte QUADRADO no cliente com Cropper.js (empacotado
    | via Vite, sem CDN, sem localStorage). Fluxo: escolher imagem → palco do Cropper
    | (aspectRatio 1) → "Salvar" gera um canvas 512x512 → blob PNG → $wire.upload('foto')
    | → ao concluir o upload, $wire.salvar() persiste no disco do tenant (reusa o caminho
    | da Aparência) e recarrega a página para o avatar do rodapé refletir. Sem foto, o
    | flux:avatar cai para as iniciais.
    */
    window.Alpine.data('cropperFoto', () => ({
        cropper: null,
        temImagem: false,
        enviando: false,

        selecionar(event) {
            const file = event.target.files && event.target.files[0];
            // Permite re-selecionar o mesmo arquivo numa próxima vez.
            event.target.value = '';
            if (!file) return;

            const url = URL.createObjectURL(file);
            this.temImagem = true;

            // Espera o x-show revelar o <img> (com layout) antes de instanciar o Cropper.
            this.$nextTick(() => {
                this.destruir();
                const img = this.$refs.imagem;
                img.src = url;
                this.cropper = new Cropper(img, {
                    aspectRatio: 1,        // recorte sempre QUADRADO
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    background: false,
                    responsive: true,
                    checkOrientation: true,
                    ready() { URL.revokeObjectURL(url); },
                });
            });
        },

        girar() {
            if (this.cropper) this.cropper.rotate(90);
        },

        salvar() {
            if (!this.cropper || this.enviando) return;
            this.enviando = true;

            const canvas = this.cropper.getCroppedCanvas({
                width: 512,
                height: 512,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            if (!canvas) { this.enviando = false; return; }

            canvas.toBlob((blob) => {
                if (!blob) { this.enviando = false; return; }
                const arquivo = new File([blob], 'foto-perfil.png', { type: 'image/png' });
                // Upload do Livewire (mesmo endpoint/disco temp já corrigidos para tenancy).
                this.$wire.upload('foto', arquivo,
                    () => { this.$wire.salvar(); },   // concluído → persiste + recarrega
                    () => { this.enviando = false; }, // erro → reabilita o botão
                );
            }, 'image/png');
        },

        destruir() {
            if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
        },

        // Chamado ao fechar o modal: descarta o palco para reabrir limpo.
        resetCropper() {
            this.destruir();
            this.temImagem = false;
            this.enviando = false;
            if (this.$refs.imagem) this.$refs.imagem.removeAttribute('src');
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

/** Lê uma CSS var da marca (definida no <body>), com fallback. */
function ngVar(nome, fallback) {
    const v = getComputedStyle(document.body).getPropertyValue(nome).trim();
    return v || fallback;
}

function opcoes(config) {
    const categorico = config.tipo === 'doughnut' || config.tipo === 'pie';
    const textoSuave = ngVar('--cor-texto-suave', '#71717a');
    const texto = ngVar('--cor-texto', '#18181b');
    const superficie = ngVar('--cor-superficie', '#ffffff');
    const grade = 'rgba(128, 128, 128, 0.15)'; // sutil e legível no claro e no escuro

    return {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: 4 },
        cutout: categorico ? '62%' : undefined, // doughnut mais elegante
        plugins: {
            legend: {
                display: config.legenda ?? categorico,
                position: 'bottom',
                labels: { boxWidth: 10, usePointStyle: true, color: textoSuave, padding: 16 },
            },
            tooltip: {
                backgroundColor: texto,
                titleColor: superficie,
                bodyColor: superficie,
                padding: 10,
                cornerRadius: 8,
                boxPadding: 4,
                displayColors: categorico,
            },
        },
        scales: categorico
            ? {}
            : {
                  x: {
                      grid: { display: false },
                      border: { display: false },
                      ticks: { color: textoSuave, autoSkip: true, maxRotation: 0 },
                  },
                  y: {
                      beginAtZero: true,
                      grid: { color: grade },
                      border: { display: false },
                      ticks: { color: textoSuave, precision: 0, maxTicksLimit: 5 },
                  },
              },
    };
}
