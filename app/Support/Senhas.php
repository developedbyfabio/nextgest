<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Regras de senha compartilhadas pela troca obrigatória (1º login) e pela
 * alteração self-service no painel — uma fonte de verdade, sem duplicar.
 */
class Senhas
{
    /** Regras de uma NOVA senha (campo `password` + `password_confirmation`). */
    public static function regrasNova(): array
    {
        return ['required', 'string', 'confirmed', Password::min(8)];
    }
}
