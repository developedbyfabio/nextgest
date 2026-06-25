<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida celular/telefone brasileiro (in-house): DDD (2 dígitos, 11–99) + número
 * de 8 ou 9 dígitos. Aceita com ou sem máscara (normaliza para dígitos). Para 11
 * dígitos (celular), exige o 9 na frente do número. Rejeita sequência repetida.
 */
class CelularBr implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $d = preg_replace('/\D+/', '', (string) $value);
        $len = strlen($d);

        $invalido = ($len !== 10 && $len !== 11)
            || (int) substr($d, 0, 2) < 11       // DDD começa em 11
            || ($len === 11 && $d[2] !== '9')    // celular: 9 após o DDD
            || preg_match('/^(\d)\1+$/', $d);     // tudo igual

        if ($invalido) {
            $fail('O :attribute informado não é um número de celular válido (DDD + 8 ou 9 dígitos).');
        }
    }
}
