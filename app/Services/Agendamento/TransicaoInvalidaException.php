<?php

declare(strict_types=1);

namespace App\Services\Agendamento;

use RuntimeException;

/**
 * Lançada ao tentar uma transição de status não permitida.
 */
class TransicaoInvalidaException extends RuntimeException
{
    public function __construct(string $message = 'Mudança de status não permitida.')
    {
        parent::__construct($message);
    }
}
