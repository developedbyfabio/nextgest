<?php

declare(strict_types=1);

namespace App\Livewire\Painel;

use App\Models\Unidade;
use App\Services\Dashboard\Metricas;
use App\Support\Aparencia;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dashboard do dono/gerente (tenant, guard `web`, permissão `ver_dashboard`).
 *
 * Indicadores e gráficos sobre dados REAIS (agendamentos/serviços/clientes).
 * Faturamento é ESTIMADO a partir de serviços concluídos (ver Metricas). O
 * profissional/recepção (sem ver_dashboard) é levado à agenda.
 */
#[Layout('components.layouts.painel')]
#[Title('Início')]
class Dashboard extends Component
{
    public string $periodo = '30d';

    public ?string $dataInicio = null;

    public ?string $dataFim = null;

    public $unidadeId = null;

    /** Falha ao calcular as métricas (mostra estado de erro recuperável). */
    public bool $erro = false;

    /** Métricas calculadas uma vez por requisição. */
    private ?array $cache = null;

    public function mount()
    {
        $user = auth('web')->user();

        // Sem visão de negócio: profissional/recepção caem na agenda.
        if (! $user->can('ver_dashboard')) {
            if ($user->can('ver_agenda') || $user->can('ver_agenda_propria')) {
                return $this->redirectRoute('painel.agenda', ['tenant' => tenant('id')], navigate: true);
            }

            abort(403);
        }

        $this->dataInicio ??= Carbon::today()->subDays(29)->toDateString();
        $this->dataFim ??= Carbon::today()->toDateString();
    }

    public function updated($property): void
    {
        if (in_array($property, ['periodo', 'unidadeId', 'dataInicio', 'dataFim'], true)) {
            $this->cache = null;
            // Atualiza os gráficos ao vivo (canvas em wire:ignore) sem recarregar.
            $this->dispatch('metricas-atualizadas', graficos: $this->dados()['graficos']);
        }
    }

    private function intervalo(): array
    {
        $hoje = Carbon::today();

        return match ($this->periodo) {
            'hoje' => [$hoje->copy()->startOfDay(), $hoje->copy()->endOfDay()],
            '7d' => [$hoje->copy()->subDays(6)->startOfDay(), $hoje->copy()->endOfDay()],
            'mes' => [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfMonth()],
            'custom' => $this->intervaloCustom(),
            default => [$hoje->copy()->subDays(29)->startOfDay(), $hoje->copy()->endOfDay()],
        };
    }

    private function intervaloCustom(): array
    {
        $hoje = Carbon::today();
        $inicio = Carbon::parse($this->dataInicio ?: $hoje)->startOfDay();
        $fim = Carbon::parse($this->dataFim ?: $hoje)->endOfDay();

        return $fim->lt($inicio) ? [$fim->copy()->startOfDay(), $inicio->copy()->endOfDay()] : [$inicio, $fim];
    }

    private function dados(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $this->erro = false;

            return $this->cache = $this->calcular();
        } catch (\Throwable $e) {
            report($e);
            $this->erro = true;

            return $this->cache = $this->estruturaVazia();
        }
    }

    /** Estrutura segura (zerada) para o estado de erro — a view nunca quebra. */
    private function estruturaVazia(): array
    {
        $vazio = ['labels' => [], 'datasets' => []];

        return [
            'inicio' => $this->intervalo()[0],
            'fim' => $this->intervalo()[1],
            'total' => 0,
            'delta' => null,
            'faturamento' => 0.0,
            'vendasPagas' => 0,
            'ticketMedio' => 0.0,
            'comissaoAPagar' => 0.0,
            'deltaFaturamento' => null,
            'clientesNovos' => 0,
            'clientesRecorrentes' => 0,
            'servicos' => collect(),
            'maisVendidos' => collect(),
            'profissionais' => [],
            'comparecimento' => ['taxa' => null, 'concluido' => 0, 'nao_compareceu' => 0, 'cancelado' => 0],
            'graficos' => [
                'faturamento' => $vazio, 'maisVendidos' => $vazio,
                'porDia' => $vazio, 'servicos' => $vazio, 'horarios' => $vazio, 'comparecimento' => $vazio,
            ],
        ];
    }

    private function calcular(): array
    {
        [$inicio, $fim] = $this->intervalo();

        $unidade = ($this->unidadeId === null || $this->unidadeId === '') ? null : (int) $this->unidadeId;
        $m = new Metricas($inicio, $fim, $unidade);

        $tema = Aparencia::doTenant();
        $cor = $tema['cor_principal'];
        $cor2 = $tema['cor_secundaria'];

        $comp = $m->comparativoTotal();
        $servicos = $m->servicosTop();
        $porDia = $m->agendamentosPorDia();
        $porHora = $m->horariosPorHora();
        $comparecimento = $m->comparecimento();

        // Faturamento REAL (vendas pagas) — Fatia 2D.
        $faturamento = $m->faturamento();
        $vendasPagas = $m->vendasPagas();
        $fatPorDia = $m->faturamentoPorDia();
        $maisVendidos = $m->maisVendidos();

        $graficos = [
            'faturamento' => [
                'labels' => $fatPorDia['labels'],
                'datasets' => [[
                    'label' => 'Faturamento (R$)',
                    'data' => $fatPorDia['valores'],
                    'borderColor' => $cor,
                    'backgroundColor' => $this->rgba($cor, 0.15),
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 2,
                ]],
            ],
            'maisVendidos' => [
                'labels' => $maisVendidos->pluck('nome')->all(),
                'datasets' => [[
                    'label' => 'Faturamento (R$)',
                    'data' => $maisVendidos->pluck('total')->all(),
                    'backgroundColor' => $cor2,
                    'borderRadius' => 6,
                ]],
            ],
            'porDia' => [
                'labels' => $porDia['labels'],
                'datasets' => [[
                    'label' => 'Agendamentos',
                    'data' => $porDia['valores'],
                    'borderColor' => $cor,
                    'backgroundColor' => $this->rgba($cor, 0.15),
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 2,
                ]],
            ],
            'servicos' => [
                'labels' => $servicos->pluck('nome')->all(),
                'datasets' => [[
                    'label' => 'Agendamentos',
                    'data' => $servicos->pluck('total')->all(),
                    'backgroundColor' => $cor,
                    'borderRadius' => 6,
                ]],
            ],
            'horarios' => [
                'labels' => $porHora['labels'],
                'datasets' => [[
                    'label' => 'Agendamentos',
                    'data' => $porHora['valores'],
                    'backgroundColor' => $cor2,
                    'borderRadius' => 4,
                ]],
            ],
            'comparecimento' => [
                'labels' => ['Concluídos', 'Não compareceu', 'Cancelados'],
                'datasets' => [[
                    'data' => [$comparecimento['concluido'], $comparecimento['nao_compareceu'], $comparecimento['cancelado']],
                    'backgroundColor' => [$cor, '#f59e0b', '#a1a1aa'],
                    'borderWidth' => 0,
                ]],
            ],
        ];

        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'total' => $comp['atual'],
            'delta' => $comp['delta'],
            'faturamento' => $faturamento,
            'vendasPagas' => $vendasPagas,
            'ticketMedio' => $m->ticketMedio(),
            'comissaoAPagar' => $m->comissaoAPagar(),
            'deltaFaturamento' => $m->comparativoFaturamento(),
            'clientesNovos' => $m->clientesNovos(),
            'clientesRecorrentes' => $m->clientesRecorrentes(),
            'servicos' => $servicos,
            'maisVendidos' => $maisVendidos,
            'profissionais' => $m->profissionaisDesempenho(),
            'comparecimento' => $comparecimento,
            'graficos' => $graficos,
        ];
    }

    /** Hex (#rgb/#rrggbb) → rgba(...) com alfa, para preenchimentos suaves. */
    private function rgba(string $hex, float $a): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return sprintf('rgba(%d, %d, %d, %s)', hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $a);
    }

    public function render(): View
    {
        $dados = $this->dados();
        $unidades = Unidade::where('ativo', true)->orderBy('nome')->get(['id', 'nome']);

        return view('livewire.painel.dashboard', [
            'd' => $dados,
            'unidades' => $unidades,
            'multiUnidade' => $unidades->count() >= 2,
            'temDados' => $dados['total'] > 0,
            'temVendas' => $dados['vendasPagas'] > 0,
        ]);
    }
}
