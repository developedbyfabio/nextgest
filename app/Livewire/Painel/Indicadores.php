<?php

declare(strict_types=1);

namespace App\Livewire\Painel;

use App\Models\Cliente;
use App\Models\User;
use App\Services\Painel\IndicadoresClientes;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Aba "Indicadores" (Fase II) — CASCA FINA sobre o motor App\Services\Painel\IndicadoresClientes
 * (Fase I). É apresentação: NÃO recalcula nada (sem SQL agregado/loop aqui). Toda métrica vem do
 * serviço, com os parâmetros que ele aceita.
 *
 * Filtros (decisão: aplicar só onde o motor aceita): período → ticket médio + retenção;
 * profissional → ticket médio. Risco e frequência são "estado atual do hábito" (todo o histórico
 * pago do cliente) — não recebem filtro. Unidade não é suportada pelo motor → sem filtro de unidade.
 *
 * Drill-in: seção expandível (uma por vez) com a lista paginada do segmento, usando a PAGINAÇÃO
 * que o próprio serviço oferece. O nome do cliente (que o serviço não retorna) é resolvido só para
 * a página atual com UMA query (whereIn) — exibição, não recálculo.
 *
 * Gate por permissão `ver_indicadores` (Dono+Gerente, D39), nunca por papel.
 */
#[Layout('components.layouts.painel')]
#[Title('Indicadores')]
class Indicadores extends Component
{
    use WithPagination;

    public string $periodo = '30d';

    public ?string $dataInicio = null;

    public ?string $dataFim = null;

    public ?int $profissionalId = null;

    /** Drill-in aberto: risco, ou um bucket de frequência (exclusivos). */
    public bool $mostrarRisco = false;

    public ?string $bucketAberto = null;

    /** Itens por página dos drill-ins (constante). */
    public const POR_PAGINA = 12;

    /** Falha ao calcular (estado de erro recuperável; a view nunca quebra). */
    public bool $erro = false;

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('ver_indicadores') ?? false, 403);

        $this->dataInicio ??= Carbon::today()->subDays(29)->toDateString();
        $this->dataFim ??= Carbon::today()->toDateString();
    }

    /** Mudou filtro → volta à 1ª página dos drill-ins. */
    public function updated(string $prop): void
    {
        if (in_array($prop, ['periodo', 'dataInicio', 'dataFim', 'profissionalId'], true)) {
            $this->resetPage();
        }
    }

    public function abrirRisco(): void
    {
        $this->bucketAberto = null;
        $this->mostrarRisco = true;
        $this->resetPage();
    }

    public function abrirBucket(string $bucket): void
    {
        if (! in_array($bucket, IndicadoresClientes::BUCKETS, true)) {
            return;
        }

        $this->mostrarRisco = false;
        $this->bucketAberto = $bucket;
        $this->resetPage();
    }

    public function fecharDrill(): void
    {
        $this->mostrarRisco = false;
        $this->bucketAberto = null;
        $this->resetPage();
    }

    /** [início, fim] do período local (mesmos presets do dashboard + 90d). */
    private function intervalo(): array
    {
        $hoje = Carbon::today();

        return match ($this->periodo) {
            'hoje' => [$hoje->copy()->startOfDay(), $hoje->copy()->endOfDay()],
            '7d' => [$hoje->copy()->subDays(6)->startOfDay(), $hoje->copy()->endOfDay()],
            '90d' => [$hoje->copy()->subDays(89)->startOfDay(), $hoje->copy()->endOfDay()],
            'mes' => [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfMonth()],
            'custom' => $this->intervaloCustom(),
            default => [$hoje->copy()->subDays(29)->startOfDay(), $hoje->copy()->endOfDay()], // 30d
        };
    }

    private function intervaloCustom(): array
    {
        $hoje = Carbon::today();
        $inicio = Carbon::parse($this->dataInicio ?: $hoje)->startOfDay();
        $fim = Carbon::parse($this->dataFim ?: $hoje)->endOfDay();

        return $fim->lt($inicio) ? [$fim->copy()->startOfDay(), $inicio->copy()->endOfDay()] : [$inicio, $fim];
    }

    /** Resolve nome dos clientes da PÁGINA atual (1 query; exibição, não recálculo). */
    private function nomesDosClientes(iterable $itens): array
    {
        $ids = collect($itens)->pluck('cliente_id')->unique()->all();

        if ($ids === []) {
            return [];
        }

        return Cliente::whereIn('id', $ids)->pluck('nome', 'id')->all();
    }

    public function render(IndicadoresClientes $svc): View
    {
        [$inicio, $fim] = $this->intervalo();
        $profId = $this->profissionalId ?: null;

        // Estrutura segura para o estado de erro (a view nunca quebra).
        $frequencia = ['sempre' => 0, 'regular' => 0, 'esporadico' => 0, 'novos' => 0];
        $ticket = 0.0;
        $retencao = ['base' => 0, 'voltaram' => 0, 'taxa' => 0.0];
        $risco = null;
        $listaDrill = null;
        $nomes = [];

        try {
            $this->erro = false;

            // CARDS — tudo do serviço (sem recálculo).
            $frequencia = $svc->frequencia();
            $ticket = $svc->ticketMedio($inicio, $fim, $profId);
            $retencao = $svc->retencao($inicio, $fim);
            $risco = $svc->emRisco(self::POR_PAGINA); // total p/ o card + lista do drill-in de risco

            // DRILL-IN (um por vez): usa a paginação do próprio serviço.
            if ($this->mostrarRisco) {
                $listaDrill = $risco;
                $nomes = $this->nomesDosClientes($risco->items());
            } elseif ($this->bucketAberto !== null) {
                $listaDrill = $svc->clientesPorBucket($this->bucketAberto, self::POR_PAGINA);
                $nomes = $this->nomesDosClientes($listaDrill->items());
            }
        } catch (\Throwable $e) {
            report($e);
            $this->erro = true;
        }

        return view('livewire.painel.indicadores', [
            'frequencia' => $frequencia,
            'ticket' => $ticket,
            'retencao' => $retencao,
            'risco' => $risco,
            'listaDrill' => $listaDrill,
            'nomes' => $nomes,
            'profissionais' => User::where('e_profissional', true)->orderBy('name')->get(['id', 'name']),
            'inicio' => $inicio,
            'fim' => $fim,
        ]);
    }
}
