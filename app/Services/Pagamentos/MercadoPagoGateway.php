<?php

declare(strict_types=1);

namespace App\Services\Pagamentos;

use RuntimeException;

/**
 * Implementação Mercado Pago (D19) — STUB.
 *
 * Estrutura pronta; a integração real com o SDK mercadopago/dx-php será feita
 * em fase posterior, após definição dos pontos em aberto (preapproval x cobrança
 * por job, estorno parcial, etc.). Por enquanto os métodos lançam exceção para
 * deixar explícito que ainda não há lógica.
 *
 * As credenciais chegam já descriptografadas (vindas do cast `encrypted` do
 * model GatewayPagamento). Nunca logar/expor estas credenciais.
 */
class MercadoPagoGateway implements GatewayPagamento
{
    /**
     * @param  array   $credenciais  ex.: ['access_token' => '...']
     * @param  string  $modo         'sandbox' | 'producao'
     */
    public function __construct(
        protected array $credenciais = [],
        protected string $modo = 'sandbox',
    ) {
    }

    public function cobrar(array $dados): array
    {
        throw new RuntimeException('MercadoPagoGateway::cobrar ainda não implementado (stub).');
    }

    public function estornar(string $transacaoId, ?float $valor = null): array
    {
        throw new RuntimeException('MercadoPagoGateway::estornar ainda não implementado (stub).');
    }

    public function criarAssinaturaRecorrente(array $dados): array
    {
        throw new RuntimeException('MercadoPagoGateway::criarAssinaturaRecorrente ainda não implementado (stub).');
    }

    public function tratarWebhook(array $payload): array
    {
        throw new RuntimeException('MercadoPagoGateway::tratarWebhook ainda não implementado (stub).');
    }
}
