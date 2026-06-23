<?php

declare(strict_types=1);

namespace App\Services\Clube;

use App\Models\AssinaturaClube;

/**
 * Implementação MANUAL/fake do gateway recorrente (Fase A). NÃO cobra nada, NÃO chama
 * API externa, NÃO tem webhook — o status da assinatura é definido na UI/seed. Existe
 * para a aba já consumir a costura GatewayRecorrente; o Mercado Pago Preapproval entra
 * como outra implementação quando houver VPS (Fase 2/3), sem mudar a aba.
 */
class GatewayRecorrenteManual implements GatewayRecorrente
{
    public function criarRecorrencia(AssinaturaClube $assinatura): ?string
    {
        // Sem gateway real: nenhuma recorrência externa é criada.
        return null;
    }

    public function cancelarRecorrencia(AssinaturaClube $assinatura): void
    {
        // No-op: não há recorrência externa para cancelar.
    }

    public function rotulo(): string
    {
        return 'Manual (sem cobrança automática)';
    }
}
