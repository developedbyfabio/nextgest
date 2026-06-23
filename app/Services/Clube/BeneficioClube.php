<?php

declare(strict_types=1);

namespace App\Services\Clube;

use App\Models\AssinaturaClube;
use App\Models\PlanoDesconto;
use App\Models\Venda;
use App\Services\Venda\Comanda;

/**
 * Aplica o benefício do Clube (v1 = DESCONTO PERCENTUAL) na comanda de um cliente com
 * assinatura ATIVA. Reusa Comanda::definirDesconto (não reescreve o núcleo da comanda);
 * só desconto, sem cobrir item/consumir cota (isso é fase futura, schema já pronto).
 *
 * Regra: só assinante ATIVO recebe (inadimplente/cancelado/pendente não).
 */
class BeneficioClube
{
    public function __construct(private readonly Comanda $comanda) {}

    /** % de desconto do Clube para o cliente (assinatura ATIVA), ou null se não houver. */
    public function percentualDoCliente(?int $clienteId): ?float
    {
        if (! $clienteId) {
            return null;
        }

        $assinatura = AssinaturaClube::ativas()
            ->where('cliente_id', $clienteId)
            ->latest('data_inicio')
            ->first();

        if (! $assinatura) {
            return null;
        }

        $desconto = PlanoDesconto::where('plano_id', $assinatura->plano_id)
            ->where('tipo_desconto', PlanoDesconto::TIPO_PERCENTUAL)
            ->where('aplica_em', PlanoDesconto::APLICA_TODOS)
            ->orderByDesc('valor')
            ->first();

        return $desconto ? (float) $desconto->valor : null;
    }

    /**
     * Aplica o desconto % do Clube na comanda do assinante ativo. Retorna o valor de
     * desconto aplicado (R$), ou null se o cliente não tem benefício ativo.
     */
    public function aplicarNaComanda(Venda $venda): ?float
    {
        $pct = $this->percentualDoCliente($venda->cliente_id);

        if ($pct === null || $pct <= 0) {
            return null;
        }

        $venda->refresh();
        $desconto = round((float) $venda->valor_bruto * $pct / 100, 2);

        $this->comanda->definirDesconto($venda, $desconto);

        return $desconto;
    }
}
