<?php

declare(strict_types=1);

namespace App\Services\Painel;

use App\Models\Venda;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de RETENÇÃO/FREQUÊNCIA dos clientes (Fase I — motor de números, SEM UI).
 * Só LEITURA, no contexto do tenant. Toda métrica é SET-BASED (GROUP BY / subconsulta) —
 * NUNCA loop por cliente (proibido N+1).
 *
 * Definições (decisões do Fabio):
 * - "Visita" = comanda PAGA (`vendas.status='paga'`), data da visita = `vendas.data`.
 *   Mesmo critério "paga + data" que App\Services\Dashboard\Metricas usa no faturamento.
 * - Intervalo médio = média dos dias entre visitas pagas consecutivas. Como a média dos
 *   gaps consecutivos telescopa para (última − primeira) / (visitas − 1), basta
 *   MIN/MAX/COUNT — sem janela/LAG (portável MySQL + SQLite).
 * - Risco ("sumindo") = ≥ MIN_VISITAS_HABITO visitas E dias_desde_ultima > intervalo × MULTIPLICADOR_RISCO.
 * - < MIN_VISITAS_HABITO visitas → bucket "novos/poucos dados" (NÃO conta como risco).
 *
 * As constantes (1.5, 3, cortes de frequência) ficam aqui, num único lugar — na Fase III
 * viram ajuste do Dono.
 *
 * Costura para o futuro (pós-VPS): toda métrica de hábito parte de agregadoBase() — a
 * fonte única do agregado por cliente. Para ler de uma TABELA pré-calculada (cálculo
 * noturno), basta sobrescrever agregadoBase() apontando para ela; a interface pública
 * (emRisco/frequencia/clientesPorBucket) não muda.
 */
class IndicadoresClientes
{
    /** Mínimo de visitas pagas para confiar no hábito (abaixo disso = "novos/poucos dados"). */
    public const MIN_VISITAS_HABITO = 3;

    /** Multiplicador do intervalo médio que define "sumindo" (atraso > intervalo × este). */
    public const MULTIPLICADOR_RISCO = 1.5;

    /** Corte (dias) do intervalo médio: ≤ este = "vai sempre" (semanal). */
    public const FREQ_SEMPRE_DIAS = 14;

    /** Corte (dias): > SEMPRE e ≤ este = "regular" (mensal); acima = "esporádico". */
    public const FREQ_REGULAR_DIAS = 35;

    /** Buckets de frequência (chaves estáveis). */
    public const BUCKETS = ['sempre', 'regular', 'esporadico', 'novos'];

    /**
     * RISCO ("sumindo"): clientes com ≥ MIN_VISITAS_HABITO visitas cujo atraso desde a
     * última visita passou do intervalo médio × MULTIPLICADOR_RISCO. Ordenado pelo mais
     * atrasado (dias além do hábito). Paginável (não materializa milhares de linhas).
     */
    public function emRisco(int $porPagina = 20): LengthAwarePaginator
    {
        $hoje = Carbon::today()->toDateString();
        $diasDesde = $this->diffDiasSql('?', 'MAX(data)');

        return $this->agregadoBase()
            ->selectRaw("$diasDesde as dias_desde_ultima", [$hoje])
            ->havingRaw('visitas >= ?', [self::MIN_VISITAS_HABITO])
            ->havingRaw('dias_desde_ultima > intervalo_medio * ?', [self::MULTIPLICADOR_RISCO])
            ->orderByRaw('dias_desde_ultima - intervalo_medio DESC')
            ->paginate($porPagina);
    }

    /**
     * FREQUÊNCIA: contagem por bucket (sempre/regular/esporádico/novos) numa única query
     * agregada (subconsulta + SUM(CASE)). "novos" = < MIN_VISITAS_HABITO visitas.
     *
     * @return array{sempre:int, regular:int, esporadico:int, novos:int}
     */
    public function frequencia(): array
    {
        $min = self::MIN_VISITAS_HABITO;

        $row = DB::query()->fromSub($this->agregadoBase(), 'c')->selectRaw(
            'SUM(CASE WHEN visitas < ? THEN 1 ELSE 0 END) as novos,
             SUM(CASE WHEN visitas >= ? AND intervalo_medio <= ? THEN 1 ELSE 0 END) as sempre,
             SUM(CASE WHEN visitas >= ? AND intervalo_medio > ? AND intervalo_medio <= ? THEN 1 ELSE 0 END) as regular,
             SUM(CASE WHEN visitas >= ? AND intervalo_medio > ? THEN 1 ELSE 0 END) as esporadico',
            [
                $min,
                $min, self::FREQ_SEMPRE_DIAS,
                $min, self::FREQ_SEMPRE_DIAS, self::FREQ_REGULAR_DIAS,
                $min, self::FREQ_REGULAR_DIAS,
            ]
        )->first();

        return [
            'sempre' => (int) ($row->sempre ?? 0),
            'regular' => (int) ($row->regular ?? 0),
            'esporadico' => (int) ($row->esporadico ?? 0),
            'novos' => (int) ($row->novos ?? 0),
        ];
    }

    /**
     * Lista (paginada) os clientes de um bucket de frequência. Bucket inválido → exceção.
     */
    public function clientesPorBucket(string $bucket, int $porPagina = 20): LengthAwarePaginator
    {
        if (! in_array($bucket, self::BUCKETS, true)) {
            throw new \InvalidArgumentException("Bucket de frequência inválido: {$bucket}");
        }

        $min = self::MIN_VISITAS_HABITO;
        $q = $this->agregadoBase();

        match ($bucket) {
            'novos' => $q->havingRaw('visitas < ?', [$min]),
            'sempre' => $q->havingRaw('visitas >= ? AND intervalo_medio <= ?', [$min, self::FREQ_SEMPRE_DIAS]),
            'regular' => $q->havingRaw('visitas >= ? AND intervalo_medio > ? AND intervalo_medio <= ?', [$min, self::FREQ_SEMPRE_DIAS, self::FREQ_REGULAR_DIAS]),
            'esporadico' => $q->havingRaw('visitas >= ? AND intervalo_medio > ?', [$min, self::FREQ_REGULAR_DIAS]),
        };

        return $q->orderByDesc('visitas')->paginate($porPagina);
    }

    /**
     * TICKET MÉDIO: média de `valor_total` das comandas pagas, com recortes opcionais por
     * período (data) e por profissional. Uma query (AVG). 0 quando não há comanda paga.
     */
    public function ticketMedio(?Carbon $inicio = null, ?Carbon $fim = null, ?int $profissionalId = null): float
    {
        $q = Venda::query()->where('status', 'paga');

        if ($inicio && $fim) {
            $q->whereBetween('data', [$inicio, $fim]);
        }

        if ($profissionalId !== null) {
            $q->where('profissional_id', $profissionalId);
        }

        return round((float) ($q->avg('valor_total') ?? 0.0), 2);
    }

    /**
     * RETENÇÃO (simples): % dos clientes que tiveram comanda paga no período ANTERIOR (de
     * mesma duração, imediatamente antes) e voltaram a ter no período ATUAL. Uma query
     * (join de dois conjuntos DISTINCT). Coorte completa fica para depois.
     *
     * @return array{base:int, voltaram:int, taxa:float, inicio:Carbon, fim:Carbon, anterior_inicio:Carbon, anterior_fim:Carbon}
     */
    public function retencao(Carbon $inicio, Carbon $fim): array
    {
        $delta = $inicio->diffInSeconds($fim);
        $antFim = $inicio->copy();                       // limite superior (exclusivo) do anterior
        $antInicio = $inicio->copy()->subSeconds($delta); // mesma duração, imediatamente antes

        $anterior = DB::table('vendas')->select('cliente_id')->distinct()
            ->where('status', 'paga')->whereNotNull('cliente_id')
            ->where('data', '>=', $antInicio)->where('data', '<', $antFim);

        $atual = DB::table('vendas')->select('cliente_id')->distinct()
            ->where('status', 'paga')->whereNotNull('cliente_id')
            ->whereBetween('data', [$inicio, $fim]);

        $row = DB::query()->fromSub($anterior, 'p')
            ->leftJoinSub($atual, 'c', 'c.cliente_id', '=', 'p.cliente_id')
            ->selectRaw('COUNT(*) as base, COUNT(c.cliente_id) as voltaram')
            ->first();

        $base = (int) ($row->base ?? 0);
        $voltaram = (int) ($row->voltaram ?? 0);

        return [
            'base' => $base,
            'voltaram' => $voltaram,
            'taxa' => $base > 0 ? round($voltaram / $base * 100, 1) : 0.0,
            'inicio' => $inicio,
            'fim' => $fim,
            'anterior_inicio' => $antInicio,
            'anterior_fim' => $antFim,
        ];
    }

    /**
     * FONTE ÚNICA do agregado por cliente sobre comandas PAGAS (clientes com ≥1 paga).
     * Colunas: cliente_id, visitas, primeira, ultima, intervalo_medio (NULL se 1 visita).
     * É a costura para, no futuro, ler de uma tabela pré-calculada sem mudar as métricas.
     */
    protected function agregadoBase(): Builder
    {
        $intervalo = '('.$this->diffDiasSql('MAX(data)', 'MIN(data)').' * 1.0 / (COUNT(*) - 1))';

        return DB::table('vendas')
            ->where('status', 'paga')
            ->whereNotNull('cliente_id')
            ->groupBy('cliente_id')
            ->selectRaw('cliente_id, COUNT(*) as visitas, MIN(data) as primeira, MAX(data) as ultima')
            ->selectRaw("CASE WHEN COUNT(*) > 1 THEN $intervalo ELSE NULL END as intervalo_medio");
    }

    /**
     * Diferença em DIAS entre duas expressões de data, portável entre MySQL e SQLite
     * (os testes rodam em SQLite; produção em MySQL). Os argumentos são fragmentos SQL
     * (ex.: 'MAX(data)', '?').
     */
    private function diffDiasSql(string $maior, string $menor): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(julianday($maior) - julianday($menor))"
            : "DATEDIFF($maior, $menor)";
    }
}
