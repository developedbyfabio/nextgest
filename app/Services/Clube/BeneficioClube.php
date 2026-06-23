<?php

declare(strict_types=1);

namespace App\Services\Clube;

use App\Models\AssinaturaClube;
use App\Models\UsoClube;
use App\Models\Venda;
use App\Services\Venda\Comanda;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Benefício do Clube (D44) = COBERTURA de serviços (100%). Para um assinante ATIVO, os
 * serviços COBERTOS pelo plano, no DIA permitido e DENTRO DO TETO, saem zerados na
 * comanda; o resto (produtos, serviço fora do plano, dia não permitido, além do teto) é
 * pago normalmente no balcão. Cada cobertura registra um `uso_clube` (consumo por
 * assinatura no período — a família divide o teto). Reusa `Comanda::recalcular` (não
 * reescreve o núcleo). Substitui o "% desconto" da Fase A (depreciado).
 */
class BeneficioClube
{
    public function __construct(private readonly Comanda $comanda) {}

    /** Assinatura ATIVA do cliente: como TITULAR ou como BENEFICIÁRIO com conta. */
    public function assinaturaDoCliente(?int $clienteId): ?AssinaturaClube
    {
        if (! $clienteId) {
            return null;
        }

        return AssinaturaClube::query()
            ->where('status', AssinaturaClube::STATUS_ATIVA)
            ->where(function ($q) use ($clienteId) {
                $q->where('cliente_id', $clienteId)
                    ->orWhereHas('beneficiarios', fn ($b) => $b->where('cliente_id', $clienteId));
            })
            ->with('plano')
            ->latest('data_inicio')
            ->first();
    }

    /** Usos consumidos pela assinatura no período (mês 'Y-m'). */
    public function usosNoPeriodo(AssinaturaClube $assinatura, string $periodo): int
    {
        return UsoClube::where('assinatura_id', $assinatura->id)
            ->where('periodo_referencia', $periodo)
            ->count();
    }

    /** Saldo de usos no período; null = ilimitado (sem teto). */
    public function saldoRestante(AssinaturaClube $assinatura, string $periodo): ?int
    {
        $plano = $assinatura->plano;

        if (! $plano || $plano->ilimitado || is_null($plano->limite_usos)) {
            return null;
        }

        return max(0, (int) $plano->limite_usos - $this->usosNoPeriodo($assinatura, $periodo));
    }

    /**
     * Aplica a cobertura na comanda. Retorna o nº de itens cobertos (0 = nada coberto:
     * sem assinatura ativa, dia não permitido, serviço fora do plano ou teto esgotado).
     */
    public function aplicarCobertura(Venda $venda): int
    {
        $assinatura = $this->assinaturaDoCliente($venda->cliente_id);

        if (! $assinatura || ! $assinatura->plano) {
            return 0;
        }

        $plano = $assinatura->plano;
        $quando = $venda->data ?? Carbon::now();

        if (! $plano->cobreDia($quando->dayOfWeek)) {
            return 0;
        }

        $cobertosIds = $plano->servicosCobertosIds();

        if ($cobertosIds === []) {
            return 0;
        }

        $periodo = $quando->format('Y-m');
        $saldo = $this->saldoRestante($assinatura, $periodo); // null = ilimitado
        $cobertos = 0;

        DB::transaction(function () use ($venda, $assinatura, $plano, $cobertosIds, $periodo, $quando, &$saldo, &$cobertos) {
            foreach ($venda->itens()->where('tipo', 'servico')->where('coberto_por_assinatura', false)->get() as $item) {
                if (! in_array((int) $item->servico_id, $cobertosIds, true)) {
                    continue;
                }

                if ($saldo !== null && $saldo <= 0) {
                    break; // teto do período esgotado
                }

                $item->update([
                    'subtotal' => 0,
                    'coberto_por_assinatura' => true,
                    'assinatura_id' => $assinatura->id,
                ]);

                UsoClube::create([
                    'assinatura_id' => $assinatura->id,
                    'plano_beneficio_id' => $plano->beneficios()->where('servico_id', $item->servico_id)->value('id'),
                    'servico_id' => $item->servico_id,
                    'venda_item_id' => $item->id,
                    'periodo_referencia' => $periodo,
                    'data' => $quando,
                ]);

                if ($saldo !== null) {
                    $saldo--;
                }
                $cobertos++;
            }

            $this->comanda->recalcular($venda);
        });

        return $cobertos;
    }
}
