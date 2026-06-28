<?php

declare(strict_types=1);

namespace App\Services\Pagamentos;

use RuntimeException;

/**
 * Falha tratável no gateway de pagamento do tenant (OAuth/cobrança). Mensagem
 * amigável (pt-BR), sem segredo — detalhe técnico só no log. D78.
 */
class PagamentoGatewayException extends RuntimeException {}
