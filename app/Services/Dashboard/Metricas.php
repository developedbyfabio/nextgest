<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Agendamento;
use App\Models\AgendamentoServico;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregações do dashboard do dono/gerente, no contexto do TENANT.
 *
 * Fonte do FATURAMENTO (Fatia 2D): vendas/comandas REAIS — soma de
 * `vendas.valor_total` das vendas `paga` no período/unidade. Atendimento sem
 * comanda NÃO vira faturamento (só conta o que foi efetivamente cobrado). Os
 * indicadores operacionais (agendamentos, comparecimento, clientes, horários,
 * serviços agendados) seguem vindo de `agendamentos`/`agendamento_servico`.
 *
 * Portabilidade: as agregações usam apenas count/sum/groupBy por coluna
 * (idênticos em SQLite de teste e MySQL de produção). Extrações de data/hora
 * (série temporal e distribuição por hora) são feitas em PHP a partir de uma
 * única consulta enxuta — evitando funções SQL específicas de cada banco.
 */
class Metricas
{
    /** Cache da consulta enxuta (data/hora + status) usada nas séries. */
    private ?Collection $linhaDoTempo = null;

    public function __construct(
        private readonly Carbon $inicio,
        private readonly Carbon $fim,
        private readonly ?int $unidadeId = null,
    ) {}

    /** Base: agendamentos cujo início cai no período (e na unidade, se filtrada). */
    private function base()
    {
        return Agendamento::query()
            ->whereBetween('data_hora_inicio', [$this->inicio, $this->fim])
            ->when($this->unidadeId, fn ($q) => $q->where('unidade_id', $this->unidadeId));
    }

    public function totalAgendamentos(): int
    {
        return $this->base()->count();
    }

    /** Comparação com o período anterior de mesma duração imediatamente antes. */
    public function comparativoTotal(): array
    {
        $dias = $this->inicio->diffInDays($this->fim) + 1;
        $fimAnterior = $this->inicio->copy()->subDay()->endOfDay();
        $inicioAnterior = $fimAnterior->copy()->subDays($dias - 1)->startOfDay();

        $anterior = Agendamento::query()
            ->whereBetween('data_hora_inicio', [$inicioAnterior, $fimAnterior])
            ->when($this->unidadeId, fn ($q) => $q->where('unidade_id', $this->unidadeId))
            ->count();

        $atual = $this->totalAgendamentos();
        $delta = $anterior > 0 ? (float) ((($atual - $anterior) / $anterior) * 100) : null;

        return ['atual' => $atual, 'anterior' => $anterior, 'delta' => $delta];
    }

    // ----- Faturamento REAL (vendas pagas) — Fatia 2D -----

    /** Base: vendas PAGAS cuja data cai no período (e na unidade, se filtrada). */
    private function baseVendas()
    {
        return Venda::query()
            ->where('status', 'paga')
            ->whereBetween('data', [$this->inicio, $this->fim])
            ->when($this->unidadeId, fn ($q) => $q->where('unidade_id', $this->unidadeId));
    }

    /** Faturamento REAL: soma de `valor_total` das vendas pagas no período. */
    public function faturamento(): float
    {
        return (float) $this->baseVendas()->sum('valor_total');
    }

    /** Número de vendas pagas no período. */
    public function vendasPagas(): int
    {
        return $this->baseVendas()->count();
    }

    /** Ticket médio = faturamento ÷ nº de vendas pagas (0 sem vendas). */
    public function ticketMedio(): float
    {
        $n = $this->vendasPagas();

        return $n > 0 ? $this->faturamento() / $n : 0.0;
    }

    /** Comissão a pagar: soma de `valor_comissao` dos itens das vendas pagas. */
    public function comissaoAPagar(): float
    {
        return (float) VendaItem::query()
            ->whereHas('venda', function ($q) {
                $q->where('status', 'paga')
                    ->whereBetween('data', [$this->inicio, $this->fim])
                    ->when($this->unidadeId, fn ($q) => $q->where('unidade_id', $this->unidadeId));
            })
            ->sum('valor_comissao');
    }

    /** Variação % do faturamento vs. período anterior de mesma duração (null se 0). */
    public function comparativoFaturamento(): ?float
    {
        $dias = $this->inicio->diffInDays($this->fim) + 1;
        $fimAnterior = $this->inicio->copy()->subDay()->endOfDay();
        $inicioAnterior = $fimAnterior->copy()->subDays($dias - 1)->startOfDay();

        $anterior = (float) Venda::query()
            ->where('status', 'paga')
            ->whereBetween('data', [$inicioAnterior, $fimAnterior])
            ->when($this->unidadeId, fn ($q) => $q->where('unidade_id', $this->unidadeId))
            ->sum('valor_total');

        return $anterior > 0 ? (($this->faturamento() - $anterior) / $anterior) * 100 : null;
    }

    /** Faturamento por dia (vendas pagas) no período, com dias zerados. */
    public function faturamentoPorDia(): array
    {
        $soma = $this->baseVendas()->get(['data', 'valor_total'])
            ->groupBy(fn ($v) => $v->data->format('Y-m-d'))
            ->map(fn ($g) => (float) $g->sum('valor_total'));

        $labels = [];
        $valores = [];

        foreach (CarbonPeriod::create($this->inicio->copy()->startOfDay(), $this->fim->copy()->startOfDay()) as $dia) {
            $labels[] = $dia->format('d/m');
            $valores[] = round((float) ($soma[$dia->format('Y-m-d')] ?? 0), 2);
        }

        return ['labels' => $labels, 'valores' => $valores];
    }

    /** Itens mais vendidos por faturamento (R$) nas vendas pagas. Top N. */
    public function maisVendidos(int $n = 6): Collection
    {
        return VendaItem::query()
            ->select('descricao', DB::raw('SUM(subtotal) as total'))
            ->whereHas('venda', function ($q) {
                $q->where('status', 'paga')
                    ->whereBetween('data', [$this->inicio, $this->fim])
                    ->when($this->unidadeId, fn ($q) => $q->where('unidade_id', $this->unidadeId));
            })
            ->groupBy('descricao')
            ->orderByDesc('total')
            ->limit($n)
            ->get()
            ->map(fn ($r) => ['nome' => $r->descricao, 'total' => (float) $r->total]);
    }

    /** Clientes cadastrados no período (clientes não têm unidade). */
    public function clientesNovos(): int
    {
        return Cliente::whereBetween('created_at', [$this->inicio, $this->fim])->count();
    }

    /** Clientes com mais de 1 agendamento no período. */
    public function clientesRecorrentes(): int
    {
        return $this->base()
            ->select('cliente_id')
            ->groupBy('cliente_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }

    /** Serviços mais agendados (exclui cancelado/não compareceu). Top N. */
    public function servicosTop(int $n = 6): Collection
    {
        return AgendamentoServico::query()
            ->select('servico_id', DB::raw('COUNT(*) as total'))
            ->whereHas('agendamento', function ($q) {
                $q->whereNotIn('status', Agendamento::STATUS_LIVRES)
                    ->whereBetween('data_hora_inicio', [$this->inicio, $this->fim])
                    ->when($this->unidadeId, fn ($q) => $q->where('unidade_id', $this->unidadeId));
            })
            ->groupBy('servico_id')
            ->orderByDesc('total')
            ->limit($n)
            ->with('servico:id,nome')
            ->get()
            ->map(fn ($r) => ['nome' => $r->servico?->nome ?? '—', 'total' => (int) $r->total]);
    }

    /** Profissionais por nº de concluídos (+ valor estimado). Top N. */
    public function profissionaisDesempenho(int $n = 5): Collection
    {
        $linhas = DB::table('agendamentos')
            ->join('agendamento_servico', 'agendamento_servico.agendamento_id', '=', 'agendamentos.id')
            ->where('agendamentos.status', 'concluido')
            ->whereBetween('agendamentos.data_hora_inicio', [$this->inicio, $this->fim])
            ->when($this->unidadeId, fn ($q) => $q->where('agendamentos.unidade_id', $this->unidadeId))
            ->groupBy('agendamentos.profissional_id')
            ->select(
                'agendamentos.profissional_id',
                DB::raw('COUNT(DISTINCT agendamentos.id) as total'),
                DB::raw('SUM(agendamento_servico.preco) as valor'),
            )
            ->orderByDesc('total')
            ->limit($n)
            ->get();

        $nomes = User::whereIn('id', $linhas->pluck('profissional_id'))->pluck('name', 'id');

        return $linhas->map(fn ($r) => [
            'nome' => $nomes[$r->profissional_id] ?? '—',
            'total' => (int) $r->total,
            'valor' => (float) $r->valor,
        ]);
    }

    /** Contagem por status (taxa de comparecimento). */
    public function comparecimento(): array
    {
        $porStatus = $this->base()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $concluido = (int) ($porStatus['concluido'] ?? 0);
        $naoCompareceu = (int) ($porStatus['nao_compareceu'] ?? 0);
        $cancelado = (int) ($porStatus['cancelado'] ?? 0);
        $resolvidos = $concluido + $naoCompareceu + $cancelado;

        return [
            'concluido' => $concluido,
            'nao_compareceu' => $naoCompareceu,
            'cancelado' => $cancelado,
            'taxa' => $resolvidos > 0 ? ($concluido / $resolvidos) * 100 : null,
        ];
    }

    /** Consulta enxuta (uma vez) para as séries por dia/hora. */
    private function linhaDoTempo(): Collection
    {
        return $this->linhaDoTempo ??= $this->base()->get(['data_hora_inicio', 'status']);
    }

    /** Agendamentos por dia no período (todos os status), com dias zerados. */
    public function agendamentosPorDia(): array
    {
        $contagem = $this->linhaDoTempo()
            ->groupBy(fn ($a) => $a->data_hora_inicio->format('Y-m-d'))
            ->map->count();

        $labels = [];
        $valores = [];

        foreach (CarbonPeriod::create($this->inicio->copy()->startOfDay(), $this->fim->copy()->startOfDay()) as $dia) {
            $labels[] = $dia->format('d/m');
            $valores[] = (int) ($contagem[$dia->format('Y-m-d')] ?? 0);
        }

        return ['labels' => $labels, 'valores' => $valores];
    }

    /** Distribuição por hora do dia (ocupantes), janela comercial 7–21h. */
    public function horariosPorHora(): array
    {
        $contagem = $this->linhaDoTempo()
            ->reject(fn ($a) => in_array($a->status, Agendamento::STATUS_LIVRES, true))
            ->groupBy(fn ($a) => (int) $a->data_hora_inicio->format('G'))
            ->map->count();

        $labels = [];
        $valores = [];

        for ($h = 7; $h <= 21; $h++) {
            $labels[] = sprintf('%02dh', $h);
            $valores[] = (int) ($contagem[$h] ?? 0);
        }

        return ['labels' => $labels, 'valores' => $valores];
    }
}
