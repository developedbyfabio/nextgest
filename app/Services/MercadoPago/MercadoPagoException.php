<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

use RuntimeException;

/**
 * Falha ao falar com a API do Mercado Pago. A mensagem é SEGURA para exibir/logar
 * (sem token, sem header de auth) — ver PreapprovalClient.
 */
class MercadoPagoException extends RuntimeException {}
