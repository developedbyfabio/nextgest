<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * 2FA por TOTP (app autenticador) — FONTE ÚNICA da criptografia/códigos. Reusada
 * pelo componente de setup (perfil + onboarding do Dono) e pelo desafio de login.
 *
 * A cripto NUNCA é feita à mão: TOTP por pragmarx/google2fa (RFC 6238) e QR por
 * bacon/bacon-qr-code (SVG inline, sem GD/Imagick). Este Support não toca o banco
 * nem expõe segredo — só gera/valida. Quem persiste (cifrado, D38) é o componente.
 */
final class DoisFatores
{
    /** Quantidade padrão de códigos de recuperação (uso único). */
    public const QTD_RECUPERACAO = 8;

    /** Tolerância de janelas (períodos de 30s) na verificação do código. */
    public const JANELA = 1;

    private static function engine(): Google2FA
    {
        return new Google2FA;
    }

    /** Gera um segredo TOTP base32 novo (não persiste — quem grava cifra). */
    public static function gerarSegredo(): string
    {
        return self::engine()->generateSecretKey();
    }

    /**
     * URL otpauth:// para o app autenticador. Emissor = "Nextgest ({slug})",
     * conta = e-mail do Dono. Tudo URL-encoded (RFC 3986).
     */
    public static function otpauthUrl(string $conta, string $segredo, string $emissor): string
    {
        $rotulo = rawurlencode($emissor).':'.rawurlencode($conta);

        $params = http_build_query([
            'secret' => $segredo,
            'issuer' => $emissor,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);

        return "otpauth://totp/{$rotulo}?{$params}";
    }

    /**
     * QR (SVG inline) do otpauth://. Remove a declaração XML para embutir direto no
     * HTML do Blade. Tamanho em px (lado).
     */
    public static function qrSvg(string $otpauthUrl, int $tamanho = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($tamanho, 0),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($otpauthUrl);

        // bacon emite uma declaração XML antes do <svg>; removida para embutir inline.
        return trim(preg_replace('/^<\?xml.*?\?'.'>\s*/s', '', $svg));
    }

    /**
     * Confere um código TOTP de 6 dígitos contra o segredo, com tolerância de janela.
     * Sanitiza a entrada (tira espaços) e nunca estoura: entrada inválida → false.
     */
    public static function verificarCodigo(string $segredo, string $codigo): bool
    {
        $codigo = self::normalizar($codigo);

        if ($segredo === '' || ! preg_match('/^\d{6}$/', $codigo)) {
            return false;
        }

        try {
            return (bool) self::engine()->verifyKey($segredo, $codigo, self::JANELA);
        } catch (\Throwable) {
            // Segredo malformado / caracteres inválidos: trata como código inválido.
            return false;
        }
    }

    /**
     * Gera N códigos de recuperação legíveis (uso único). Formato XXXXX-XXXXX
     * (maiúsculas/dígitos), sem caracteres ambíguos no alfabeto do Str::random.
     *
     * @return list<string>
     */
    public static function gerarCodigosRecuperacao(int $quantidade = self::QTD_RECUPERACAO): array
    {
        $codigos = [];

        for ($i = 0; $i < $quantidade; $i++) {
            $codigos[] = strtoupper(Str::random(5).'-'.Str::random(5));
        }

        return $codigos;
    }

    /** Normaliza um código digitado: remove espaços nas pontas e internos. */
    public static function normalizar(string $codigo): string
    {
        return str_replace(' ', '', trim($codigo));
    }
}
