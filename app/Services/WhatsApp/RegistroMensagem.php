<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\MensagemWhatsapp;

/**
 * Registra uma linha no LOG de mensagens de WhatsApp (Controle de mensagens, D83).
 * Centraliza a criação para garantir que o `conteudo` nunca guarde uma CREDENCIAL VIVA:
 * links (ex.: o link ASSINADO da avaliação, D81) são mascarados antes de gravar.
 * Registra ENVIO (não recebimento) e não cruza com `avaliacoes` (anonimato D51).
 */
class RegistroMensagem
{
    /** @param  array<string, mixed>  $attrs */
    public static function registrar(array $attrs): MensagemWhatsapp
    {
        if (array_key_exists('conteudo', $attrs) && is_string($attrs['conteudo'])) {
            $attrs['conteudo'] = self::mascararLinks($attrs['conteudo']);
        }

        return MensagemWhatsapp::create($attrs);
    }

    /** Substitui qualquer URL por `[link]` — não persiste link assinado/credencial. */
    public static function mascararLinks(string $texto): string
    {
        return preg_replace('#https?://\S+#i', '[link]', $texto) ?? $texto;
    }
}
