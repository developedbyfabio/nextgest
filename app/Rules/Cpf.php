<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida CPF brasileiro (in-house — sem dependência externa; rede de build restrita).
 *
 * Aceita com ou sem máscara (normaliza para dígitos): exige 11 dígitos, rejeita
 * sequências repetidas (000…, 111…) e confere os dois dígitos verificadores.
 * O armazenamento normalizado (só dígitos) é responsabilidade de quem grava.
 */
class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $d = preg_replace('/\D+/', '', (string) $value);

        if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) {
            $fail('O :attribute informado não é um CPF válido.');

            return;
        }

        foreach ([9, 10] as $pos) {
            $soma = 0;
            for ($i = 0; $i < $pos; $i++) {
                $soma += (int) $d[$i] * (($pos + 1) - $i);
            }
            $resto = ($soma * 10) % 11;
            $digito = $resto === 10 ? 0 : $resto;

            if ($digito !== (int) $d[$pos]) {
                $fail('O :attribute informado não é um CPF válido.');

                return;
            }
        }
    }
}
