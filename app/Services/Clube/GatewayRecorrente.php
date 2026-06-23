<?php

declare(strict_types=1);

namespace App\Services\Clube;

use App\Models\AssinaturaClube;

/**
 * Costura do gateway de cobrança RECORRENTE do Clube (mensalidade da assinatura).
 *
 * É a fonte trocável: hoje a única implementação é GatewayRecorrenteManual (status
 * manual, sem cobrar nada). Quando houver VPS, o Mercado Pago **Preapproval** + webhook
 * será OUTRA implementação desta mesma interface — sem mudar a aba nem o serviço de
 * assinaturas (mesma ideia do agregadoBase() dos Indicadores). Binding em
 * App\Providers\AppServiceProvider.
 */
interface GatewayRecorrente
{
    /**
     * Cria a recorrência no gateway e devolve o id externo (ex.: Preapproval id do MP),
     * ou null quando não há gateway (manual). NÃO chama API externa na Fase A.
     */
    public function criarRecorrencia(AssinaturaClube $assinatura): ?string;

    /** Cancela a recorrência no gateway. No manual é no-op. */
    public function cancelarRecorrencia(AssinaturaClube $assinatura): void;

    /** Identificação curta (para UI/log). */
    public function rotulo(): string;
}
