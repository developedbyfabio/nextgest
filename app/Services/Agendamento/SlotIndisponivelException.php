<?php

declare(strict_types=1);

namespace App\Services\Agendamento;

use RuntimeException;

/**
 * Lançada quando o horário escolhido não está mais disponível no momento da
 * confirmação (ex.: outro cliente pegou o mesmo slot).
 */
class SlotIndisponivelException extends RuntimeException
{
    public function __construct(string $message = 'Este horário acabou de ser preenchido. Escolha outro.')
    {
        parent::__construct($message);
    }
}
