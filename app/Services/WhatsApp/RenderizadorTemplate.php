<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

/**
 * Renderiza um template de mensagem trocando placeholders `{var}` pelos valores (Fatia 3,
 * D77). Seguro por construção:
 *  - só substitui as variáveis CONHECIDAS passadas; um `{xpto}` desconhecido fica
 *    LITERAL (nunca quebra/erro);
 *  - é só `str_replace` (sem eval/expressão) → não há injeção de código;
 *  - valores são normalizados para string e sem caracteres de controle.
 */
class RenderizadorTemplate
{
    /**
     * @param  array<string, string|int|float|null>  $vars
     */
    public static function render(string $template, array $vars): string
    {
        $busca = [];
        $troca = [];

        foreach ($vars as $chave => $valor) {
            $busca[] = '{'.$chave.'}';
            $troca[] = self::normalizar((string) ($valor ?? ''));
        }

        // Variáveis desconhecidas no template não estão em $busca → ficam literais.
        return str_replace($busca, $troca, $template);
    }

    /** Remove caracteres de controle (mantém quebras de linha e tab). */
    private static function normalizar(string $valor): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valor) ?? $valor;
    }
}
