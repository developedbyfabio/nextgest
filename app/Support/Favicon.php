<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Processamento do favicon do tenant (D90). Diferente das demais imagens da
 * Aparência (logo/cabeçalho/fundo, guardadas CRUAS), o favicon é NORMALIZADO no
 * upload: a imagem do dono pode ser grande/pesada/em qualquer formato, mas o
 * ícone da aba precisa ser pequeno e nítido. Aqui reduzimos para um quadrado
 * TAMANHO×TAMANHO ("contain": cabe inteira, proporção preservada, centralizada
 * sobre fundo TRANSPARENTE) e convertemos para PNG.
 *
 * Usa GD — extensão JÁ disponível no servidor (sem pacote novo, sem download no
 * build). Grava no MESMO disco/pasta do logo (storage/tenant{id}/app/public/
 * aparencia), com nome ÚNICO por upload → nova URL a cada troca → cache-busting
 * herdado (o navegador cacheia favicon agressivamente; a URL versionada força a
 * atualização). Servido por App\Http\Controllers\TenantArquivoController.
 */
class Favicon
{
    /** Lado do favicon quadrado gerado (px). 32 cobre a aba na maioria dos DPIs. */
    public const TAMANHO = 32;

    /**
     * Processa o upload num favicon PNG TAMANHO×TAMANHO e grava no disco público
     * do tenant. Retorna o caminho salvo (ex.: "aparencia/favicon-<hash>.png").
     *
     * @param  \Illuminate\Http\UploadedFile  $arquivo  Upload (temporário) validado.
     */
    public static function processar($arquivo): string
    {
        $png = self::gerarPng($arquivo->get());

        $caminho = 'aparencia/favicon-'.Str::random(40).'.png';
        Storage::disk('public')->put($caminho, $png);

        return $caminho;
    }

    /**
     * Gera o PNG quadrado (contain, fundo transparente) a partir do conteúdo bruto
     * de uma imagem. Lança RuntimeException se o conteúdo não for uma imagem que o
     * GD decodifique (defesa; a validação `image` do formulário já barra o grosso).
     */
    private static function gerarPng(string $conteudo): string
    {
        $src = @imagecreatefromstring($conteudo);
        if ($src === false) {
            throw new RuntimeException('Imagem inválida para o favicon.');
        }

        $lado = self::TAMANHO;
        $largura = imagesx($src);
        $altura = imagesy($src);

        // Escala "contain": a imagem inteira cabe no quadrado, sem distorcer.
        $escala = min($lado / $largura, $lado / $altura);
        $novaLargura = max(1, (int) round($largura * $escala));
        $novaAltura = max(1, (int) round($altura * $escala));
        $deslocX = (int) (($lado - $novaLargura) / 2);
        $deslocY = (int) (($lado - $novaAltura) / 2);

        $dst = imagecreatetruecolor($lado, $lado);
        // Preserva transparência: preenche com transparente ANTES de compor.
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparente = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $lado - 1, $lado - 1, $transparente);
        imagealphablending($dst, true);

        imagecopyresampled($dst, $src, $deslocX, $deslocY, 0, 0, $novaLargura, $novaAltura, $largura, $altura);

        ob_start();
        imagepng($dst);
        $png = ob_get_clean();

        // (Sem imagedestroy: no-op desde o PHP 8.0 e deprecado no 8.5 — o GC libera.)

        if ($png === false || $png === '') {
            throw new RuntimeException('Falha ao gerar o favicon.');
        }

        return $png;
    }
}
