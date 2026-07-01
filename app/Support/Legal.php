<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Metadados dos documentos legais do portal (D93) — Política de Privacidade e
 * Termos de Uso. Conteúdo é ÚNICO e compartilhado por todos os tenants (não
 * editável por tenant nesta fatia); só o slug da URL muda. Versão/data ficam aqui
 * (fonte única) para os dois documentos exibirem o mesmo rótulo.
 */
class Legal
{
    /** Versão vigente dos documentos (subir ao alterar o texto). */
    public const VERSAO = '1.0';

    /** Data (ISO) da última atualização — usada no <time> e no rótulo pt-BR. */
    public const ATUALIZADO_EM = '2026-07-01';

    /** Rótulo pt-BR da data de atualização (ex.: "1 de julho de 2026"). */
    public static function atualizadoEmLabel(): string
    {
        return Carbon::parse(self::ATUALIZADO_EM)->translatedFormat('j \d\e F \d\e Y');
    }
}
