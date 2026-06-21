<?php

declare(strict_types=1);

namespace App\Services\Venda;

use RuntimeException;

/**
 * Lançada ao tentar alterar/pagar uma comanda que não está mais `aberta`.
 */
class VendaNaoEditavelException extends RuntimeException
{
}
