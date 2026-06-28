<?php

declare(strict_types=1);

namespace App\Services\Pagamentos;

/**
 * Contrato comum de um gateway de pagamento (padrão adapter — D19).
 *
 * Cada provedor (Mercado Pago, Asaas, ...) implementa esta interface. O resto
 * do sistema fala só com este contrato, então trocar/adicionar provedor não
 * mexe na regra de negócio.
 *
 * Segurança (D21): nunca trafegar/armazenar dados de cartão — só o token
 * devolvido pelo gateway. As credenciais ficam criptografadas no model
 * App\Models\GatewayPagamento (cast `encrypted`).
 *
 * Os parâmetros/retornos usam arrays simples por ora (esqueleto). DTOs tipados
 * podem ser introduzidos quando a integração real for implementada.
 */
interface GatewayPagamento
{
    /**
     * Cria uma cobrança (pix, cartão, etc.).
     *
     * @param  array  $dados  valor, método, descrição, cliente, token de cartão...
     * @return array status, gateway_transacao_id, pix_copia_cola, link_pagamento...
     */
    public function cobrar(array $dados): array;

    /**
     * Estorna (total ou parcial) uma transação aprovada.
     *
     * @param  string  $transacaoId  id da transação no gateway
     * @param  float|null  $valor  null = estorno total
     */
    public function estornar(string $transacaoId, ?float $valor = null): array;

    /**
     * Cria uma assinatura recorrente (mensalidade do clube).
     *
     * @param  array  $dados  plano, valor, cartão tokenizado, periodicidade...
     * @return array gateway_assinatura_id, status...
     */
    public function criarAssinaturaRecorrente(array $dados): array;

    /**
     * Interpreta um webhook recebido do gateway e devolve um resultado
     * normalizado (evento, id da transação, novo status...).
     *
     * @param  array  $payload  corpo do webhook
     */
    public function tratarWebhook(array $payload): array;
}
