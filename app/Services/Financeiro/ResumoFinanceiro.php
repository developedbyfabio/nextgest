<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Models\Venda;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Números do negócio (Financeiro v1) — banco do TENANT. Só LEITURA/agregação set-based
 * sobre o que já existe (comandas pagas, comissões, custo de produto). NUNCA loop.
 *
 * FONTE DE VERDADE ÚNICA da receita: mesmo critério do App\Services\Dashboard\Metricas
 * — comanda PAGA (`vendas.status='paga'`) + `vendas.data` no período (+ unidade). Aqui há
 * um filtro EXTRA opcional por profissional (responsável da comanda). Com profissional
 * nulo, o faturamento é IDÊNTICO ao do dashboard (garantido por teste).
 *
 * NÃO é cálculo de imposto/DAS/regime; NÃO promete lucro LÍQUIDO (sem despesas — v2). O
 * lucro BRUTO = receita − comissões − CPV (CPV usa o custo de compra ATUAL do produto,
 * sem snapshot histórico; produtos sem custo não entram).
 */
final class ResumoFinanceiro
{
    public function __construct(
        private readonly Carbon $inicio,
        private readonly Carbon $fim,
        private readonly ?int $profissionalId = null,
        private readonly ?int $unidadeId = null,
    ) {}

    /** Vendas PAGAS do período (mesmo critério do Metricas) + filtros opcionais. */
    private function baseVendas()
    {
        return Venda::query()
            ->where('status', 'paga')
            ->whereBetween('data', [$this->inicio, $this->fim])
            ->when($this->unidadeId, fn ($q) => $q->where('unidade_id', $this->unidadeId))
            ->when($this->profissionalId, fn ($q) => $q->where('profissional_id', $this->profissionalId));
    }

    /** Itens de vendas pagas do período (join para comissões/CPV), mesmos filtros. */
    private function baseItens()
    {
        return DB::table('venda_itens as vi')
            ->join('vendas as v', 'v.id', '=', 'vi.venda_id')
            ->where('v.status', 'paga')
            ->whereBetween('v.data', [$this->inicio, $this->fim])
            ->when($this->unidadeId, fn ($q) => $q->where('v.unidade_id', $this->unidadeId))
            ->when($this->profissionalId, fn ($q) => $q->where('v.profissional_id', $this->profissionalId));
    }

    /**
     * Faturamento + nº de vendas + ticket médio numa única query agregada.
     *
     * @return array{faturamento: float, vendas: int, ticketMedio: float}
     */
    public function totais(): array
    {
        $linha = $this->baseVendas()
            ->selectRaw('COALESCE(SUM(valor_total), 0) as faturamento, COUNT(*) as vendas')
            ->first();

        $faturamento = (float) ($linha->faturamento ?? 0);
        $vendas = (int) ($linha->vendas ?? 0);

        return [
            'faturamento' => round($faturamento, 2),
            'vendas' => $vendas,
            'ticketMedio' => $vendas > 0 ? round($faturamento / $vendas, 2) : 0.0,
        ];
    }

    public function faturamento(): float
    {
        return $this->totais()['faturamento'];
    }

    /** Comissões geradas pelas vendas pagas do período (snapshot por item). */
    public function comissoes(): float
    {
        return round((float) $this->baseItens()->sum('vi.valor_comissao'), 2);
    }

    /**
     * CPV = Σ (custo de compra ATUAL × quantidade) dos itens-PRODUTO vendidos. Sem
     * snapshot histórico (ressalva na tela); produtos sem `preco_custo` contribuem 0.
     */
    public function cpv(): float
    {
        $valor = $this->baseItens()
            ->join('produtos as p', 'p.id', '=', 'vi.produto_id')
            ->where('vi.tipo', 'produto')
            ->sum(DB::raw('p.preco_custo * vi.quantidade'));

        return round((float) $valor, 2);
    }

    /** Lucro BRUTO (v1) = receita − comissões − CPV. NÃO é líquido (despesas = v2). */
    public function lucroBruto(): float
    {
        return round($this->faturamento() - $this->comissoes() - $this->cpv(), 2);
    }

    /**
     * Recebimentos por forma de pagamento das vendas pagas do período (pagamentos
     * aprovados). A soma das formas == faturamento. Filtro opcional por forma.
     *
     * @return array<string, float> metodo => total
     */
    public function recebimentosPorForma(?string $forma = null): array
    {
        return DB::table('pagamentos as pg')
            ->join('vendas as v', 'v.id', '=', 'pg.venda_id')
            ->where('pg.status', 'aprovado')
            ->where('v.status', 'paga')
            ->whereBetween('v.data', [$this->inicio, $this->fim])
            ->when($this->unidadeId, fn ($q) => $q->where('v.unidade_id', $this->unidadeId))
            ->when($this->profissionalId, fn ($q) => $q->where('v.profissional_id', $this->profissionalId))
            ->when($forma, fn ($q) => $q->where('pg.metodo', $forma))
            ->groupBy('pg.metodo')
            ->selectRaw('pg.metodo, COALESCE(SUM(pg.valor), 0) as total')
            ->pluck('total', 'metodo')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    /**
     * Série de faturamento por DIA no período (uma query; data driver-aware). Dias sem
     * venda não aparecem — a view trata como zero ao desenhar.
     *
     * @return array<string, float> 'Y-m-d' => total
     */
    public function faturamentoPorDia(): array
    {
        $dia = $this->diaSql('data');

        return $this->baseVendas()
            ->groupByRaw($dia)
            ->orderByRaw($dia)
            ->selectRaw("{$dia} as dia, COALESCE(SUM(valor_total), 0) as total")
            ->pluck('total', 'dia')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    /** Expressão SQL "Y-m-d" da coluna, portável MySQL (DATE) / SQLite (date). */
    private function diaSql(string $coluna): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "date({$coluna})"
            : "DATE({$coluna})";
    }
}
