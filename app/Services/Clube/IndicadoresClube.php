<?php

declare(strict_types=1);

namespace App\Services\Clube;

use App\Models\AssinaturaClube;
use App\Models\EventoAssinaturaClube;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores do Clube (banco do TENANT). Tudo SET-BASED (GROUP BY / contagem agregada),
 * nunca loop por assinante — contagem de query constante (teste de teto). "Novos" e
 * "cancelados" saem dos EVENTOS (fonte da evolução/churn); "ativos"/"inadimplentes" do
 * status atual da assinatura.
 */
class IndicadoresClube
{
    /** Assinantes com status ativo agora. */
    public function assinantesAtivos(): int
    {
        return AssinaturaClube::ativas()->count();
    }

    /** Novos no mês = eventos `criada` no mês de $ref (padrão: mês atual). */
    public function novosNoMes(?Carbon $ref = null): int
    {
        return $this->contarEventosNoMes(EventoAssinaturaClube::TIPO_CRIADA, $ref);
    }

    /** Cancelamentos no mês = eventos `cancelada` no mês (base do churn). */
    public function canceladosNoMes(?Carbon $ref = null): int
    {
        return $this->contarEventosNoMes(EventoAssinaturaClube::TIPO_CANCELADA, $ref);
    }

    private function contarEventosNoMes(string $tipo, ?Carbon $ref): int
    {
        $ref ??= Carbon::now();

        return EventoAssinaturaClube::where('tipo', $tipo)
            ->whereBetween('ocorrido_em', [$ref->copy()->startOfMonth(), $ref->copy()->endOfMonth()])
            ->count();
    }

    /**
     * Lista (paginada) os inadimplentes para cobrança: cliente, plano, desde quando
     * (data_inicio) e valor (preco_contratado). Eager load → contagem constante.
     */
    public function inadimplentes(int $porPagina = 12): LengthAwarePaginator
    {
        return AssinaturaClube::inadimplentes()
            ->with(['cliente:id,nome,telefone', 'plano:id,nome'])
            ->orderBy('data_inicio')
            ->paginate($porPagina);
    }

    /**
     * Evolução das assinaturas: entradas (criada/reativada) × saídas (cancelada) por mês,
     * nos últimos $meses. UMA query agregada (GROUP BY mês); meses sem evento entram
     * zerados (preenchidos em PHP, limitado por $meses). `saldo` = entradas − saídas.
     *
     * @return Collection<int, array{mes:string, entradas:int, saidas:int, saldo:int}>
     */
    public function evolucao(int $meses = 6): Collection
    {
        $inicio = Carbon::now()->startOfMonth()->subMonths($meses - 1);
        $mesExpr = $this->mesSql('ocorrido_em');

        // UMA query agregada por mês (entradas/saídas). Meses faltantes são preenchidos abaixo.
        $mapa = EventoAssinaturaClube::query()
            ->where('ocorrido_em', '>=', $inicio)
            ->selectRaw("{$mesExpr} as mes")
            ->selectRaw('SUM(CASE WHEN tipo IN (?, ?) THEN 1 ELSE 0 END) as entradas', [
                EventoAssinaturaClube::TIPO_CRIADA,
                EventoAssinaturaClube::TIPO_REATIVADA,
            ])
            ->selectRaw('SUM(CASE WHEN tipo = ? THEN 1 ELSE 0 END) as saidas', [
                EventoAssinaturaClube::TIPO_CANCELADA,
            ])
            ->groupBy('mes')
            ->get()
            ->keyBy('mes');

        $serie = collect();
        for ($i = 0; $i < $meses; $i++) {
            $mes = $inicio->copy()->addMonths($i)->format('Y-m');
            $linha = $mapa->get($mes);
            $entradas = (int) ($linha->entradas ?? 0);
            $saidas = (int) ($linha->saidas ?? 0);
            $serie->push([
                'mes' => $mes,
                'entradas' => $entradas,
                'saidas' => $saidas,
                'saldo' => $entradas - $saidas,
            ]);
        }

        return $serie;
    }

    /** Expressão SQL "YYYY-MM" da coluna, portável MySQL (DATE_FORMAT) / SQLite (strftime). */
    private function mesSql(string $coluna): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$coluna})"
            : "DATE_FORMAT({$coluna}, '%Y-%m')";
    }
}
