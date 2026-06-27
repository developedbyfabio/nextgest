<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use RuntimeException;

/**
 * Falha tratável ao falar com o WhatsApp (Evolution). Mensagem amigável (pt-BR),
 * sem segredo — o detalhe técnico vai só para o log.
 */
class WhatsAppException extends RuntimeException {}
