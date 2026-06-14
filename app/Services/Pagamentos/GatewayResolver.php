<?php

declare(strict_types=1);

namespace App\Services\Pagamentos;

use App\Models\GatewayPagamento as GatewayConfig;
use InvalidArgumentException;

/**
 * Resolve a implementação de gateway (adapter) a partir do registro de
 * configuração do tenant (App\Models\GatewayPagamento).
 *
 * Para adicionar um provedor: criar a classe que implementa GatewayPagamento e
 * registrá-la no mapa $provedores.
 */
class GatewayResolver
{
    /**
     * provedor (coluna gateways_pagamento.provedor) => classe que implementa GatewayPagamento.
     *
     * @var array<string, class-string<GatewayPagamento>>
     */
    protected array $provedores = [
        'mercadopago' => MercadoPagoGateway::class,
        // 'asaas' => AsaasGateway::class, // futuro
    ];

    /**
     * Instancia o adapter para um registro de gateway do tenant.
     */
    public function para(GatewayConfig $config): GatewayPagamento
    {
        $classe = $this->provedores[$config->provedor] ?? null;

        if ($classe === null) {
            throw new InvalidArgumentException("Provedor de pagamento não suportado: {$config->provedor}");
        }

        // Convenção: todo adapter recebe (array $credenciais, string $modo).
        return new $classe($config->credenciais ?? [], $config->modo ?? 'sandbox');
    }

    /**
     * Resolve o gateway PADRÃO e ativo do tenant atual.
     */
    public function padraoDoTenant(): GatewayPagamento
    {
        $config = GatewayConfig::query()
            ->where('ativo', true)
            ->where('padrao', true)
            ->firstOrFail();

        return $this->para($config);
    }
}
