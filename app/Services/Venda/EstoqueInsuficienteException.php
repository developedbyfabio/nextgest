<?php

declare(strict_types=1);

namespace App\Services\Venda;

use RuntimeException;

/**
 * Lançada ao tentar vender/pagar um produto acima do estoque da unidade.
 */
class EstoqueInsuficienteException extends RuntimeException
{
}
