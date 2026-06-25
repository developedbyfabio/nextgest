<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida CNPJ brasileiro (in-house — sem dependência externa).
 *
 * Aceita com ou sem máscara (normaliza para dígitos): exige 14 dígitos, rejeita
 * sequências repetidas e confere os dois dígitos verificadores (pesos 5..2 / 6..2).
 */
class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $d = preg_replace('/\D+/', '', (string) $value);

        if (strlen($d) !== 14 || preg_match('/^(\d)\1{13}$/', $d)) {
            $fail('O :attribute informado não é um CNPJ válido.');

            return;
        }

        $pesos = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([12, 13] as $pos) {
            // Para o 1º dígito usa os últimos 12 pesos; para o 2º, os 13 pesos.
            $fatias = array_slice($pesos, 13 - $pos);
            $soma = 0;
            for ($i = 0; $i < $pos; $i++) {
                $soma += (int) $d[$i] * $fatias[$i];
            }
            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;

            if ($digito !== (int) $d[$pos]) {
                $fail('O :attribute informado não é um CNPJ válido.');

                return;
            }
        }
    }
}
