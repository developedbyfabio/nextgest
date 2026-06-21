<?php

declare(strict_types=1);

namespace App\Services\Venda;

use RuntimeException;

/**
 * Lançada quando os pagamentos informados não fecham com o total da comanda
 * (soma diferente do `valor_total`, valor não-positivo ou método inválido).
 */
class PagamentoInvalidoException extends RuntimeException
{
}
