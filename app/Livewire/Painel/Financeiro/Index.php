<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Financeiro;

use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Financeiro\ResumoFinanceiro;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Financeiro v1 — números do negócio (faturamento, recebimentos, lucro BRUTO) por
 * período, prontos pro contador. CASCA sobre App\Services\Financeiro\ResumoFinanceiro
 * (mesmo critério de receita do dashboard → os números BATEM). Gate `ver_financeiro`
 * (só Dono, D39), nunca hasRole.
 *
 * NÃO é cálculo de imposto/DAS/regime; NÃO promete lucro líquido (despesas = v2). O
 * banner de responsabilidade fica fixo na tela e no export.
 */
#[Layout('components.layouts.painel')]
#[Title('Financeiro')]
class Index extends Component
{
    public const AVISO = 'Estes são os números do seu negócio para organização e para entregar ao seu contador. Não é cálculo de impostos nem substitui um contador.';

    public string $periodo = '30d';

    public ?string $dataInicio = null;

    public ?string $dataFim = null;

    public ?int $profissionalId = null;

    public ?int $unidadeId = null;

    public string $forma = '';

    public bool $erro = false;

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('ver_financeiro') ?? false, 403);

        $this->dataInicio ??= Carbon::today()->subDays(29)->toDateString();
        $this->dataFim ??= Carbon::today()->toDateString();
    }

    /** [início, fim] do período (mesmos presets do dashboard/indicadores). */
    private function intervalo(): array
    {
        $hoje = Carbon::today();

        return match ($this->periodo) {
            'hoje' => [$hoje->copy()->startOfDay(), $hoje->copy()->endOfDay()],
            '7d' => [$hoje->copy()->subDays(6)->startOfDay(), $hoje->copy()->endOfDay()],
            '90d' => [$hoje->copy()->subDays(89)->startOfDay(), $hoje->copy()->endOfDay()],
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

    private function servico(): ResumoFinanceiro
    {
        [$inicio, $fim] = $this->intervalo();

        return new ResumoFinanceiro($inicio, $fim, $this->profissionalId ?: null, $this->unidadeId ?: null);
    }

    public function exportarCsv(): StreamedResponse
    {
        abort_unless(auth('web')->user()?->can('ver_financeiro') ?? false, 403);

        [$inicio, $fim] = $this->intervalo();
        $r = $this->servico();
        $totais = $r->totais();
        $recebimentos = $r->recebimentosPorForma($this->forma ?: null);
        $comissoes = $r->comissoes();
        $cpv = $r->cpv();

        $tenant = tenant('nome') ?? tenant('id');
        $periodo = $inicio->format('d/m/Y').' a '.$fim->format('d/m/Y');

        return response()->streamDownload(function () use ($tenant, $periodo, $totais, $recebimentos, $comissoes, $cpv, $r) {
            $saida = fopen('php://output', 'w');
            fputcsv($saida, ['Nextgest — Financeiro']);
            fputcsv($saida, ['Estabelecimento', $tenant]);
            fputcsv($saida, ['Período', $periodo]);
            fputcsv($saida, ['Aviso', self::AVISO]);
            fputcsv($saida, []);
            fputcsv($saida, ['Indicador', 'Valor (R$)']);
            fputcsv($saida, ['Faturamento (receita bruta)', number_format($totais['faturamento'], 2, ',', '.')]);
            foreach ($recebimentos as $metodo => $valor) {
                fputcsv($saida, ['  Recebido — '.(Pagamento::METODO_LABEL[$metodo] ?? $metodo), number_format($valor, 2, ',', '.')]);
            }
            fputcsv($saida, ['Comissões', number_format($comissoes, 2, ',', '.')]);
            fputcsv($saida, ['CPV (custo de produto vendido)', number_format($cpv, 2, ',', '.')]);
            fputcsv($saida, ['Lucro bruto (sem despesas)', number_format($r->lucroBruto(), 2, ',', '.')]);
            fputcsv($saida, ['Nº de vendas', $totais['vendas']]);
            fputcsv($saida, ['Ticket médio', number_format($totais['ticketMedio'], 2, ',', '.')]);
            fclose($saida);
        }, 'financeiro-'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render(): View
    {
        [$inicio, $fim] = $this->intervalo();

        $totais = ['faturamento' => 0.0, 'vendas' => 0, 'ticketMedio' => 0.0];
        $recebimentos = [];
        $comissoes = 0.0;
        $cpv = 0.0;
        $lucroBruto = 0.0;
        $serie = [];

        try {
            $this->erro = false;
            $r = $this->servico();
            $totais = $r->totais();
            $recebimentos = $r->recebimentosPorForma($this->forma ?: null);
            $comissoes = $r->comissoes();
            $cpv = $r->cpv();
            $lucroBruto = $r->lucroBruto();
            $serie = $r->faturamentoPorDia();
        } catch (\Throwable $e) {
            report($e);
            $this->erro = true;
        }

        $unidades = Unidade::where('ativo', true)->orderBy('nome')->get(['id', 'nome']);

        return view('livewire.painel.financeiro.index', [
            'inicio' => $inicio,
            'fim' => $fim,
            'totais' => $totais,
            'recebimentos' => $recebimentos,
            'comissoes' => $comissoes,
            'cpv' => $cpv,
            'lucroBruto' => $lucroBruto,
            'serie' => $serie,
            'temCusto' => Produto::where('preco_custo', '>', 0)->exists(),
            'metodos' => Pagamento::METODO_LABEL,
            'profissionais' => User::where('e_profissional', true)->where('ativo', true)->orderBy('name')->get(['id', 'name']),
            'unidades' => $unidades,
            'multiUnidade' => $unidades->count() >= 2,
            'aviso' => self::AVISO,
        ]);
    }
}
