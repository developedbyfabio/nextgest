<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

use Illuminate\Http\Request;

/**
 * Valida a assinatura `x-signature` dos webhooks do Mercado Pago (D62). Item nº 1 de
 * segurança: o endpoint é PÚBLICO — sem isto, qualquer um forjaria "pagou".
 *
 * Algoritmo (doc oficial): x-signature = "ts=<ts>,v1=<hash>"; manifest literal
 * `id:{data.id};request-id:{x-request-id};ts:{ts};` (data.id em lowercase);
 * HMAC-SHA256 hex com o segredo do webhook; comparação timing-safe (hash_equals).
 *
 * Segredo só via config('mercadopago.webhook_secret'); ausente → INVÁLIDO (rejeita).
 */
class ValidadorWebhook
{
    public function valido(Request $request): bool
    {
        $secret = (string) config('mercadopago.webhook_secret');

        if ($secret === '') {
            return false; // sem segredo configurado → não confia em nada (seguro)
        }

        [$ts, $v1] = $this->extrairTsV1((string) $request->header('x-signature', ''));

        if ($ts === null || $v1 === null) {
            return false;
        }

        $requestId = (string) $request->header('x-request-id', '');
        $dataId = strtolower($this->dataId($request));

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $esperado = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($esperado, $v1);
    }

    /** O valor do data.id (idêntico em query ou corpo). PHP troca o ponto por "_" no query. */
    public function dataId(Request $request): string
    {
        return (string) (
            $request->query('data_id')
            ?? $request->input('data.id')
            ?? $request->query('id')
            ?? ''
        );
    }

    /** @return array{0: ?string, 1: ?string} [ts, v1] */
    private function extrairTsV1(string $assinatura): array
    {
        $ts = null;
        $v1 = null;

        foreach (explode(',', $assinatura) as $parte) {
            [$chave, $valor] = array_pad(explode('=', trim($parte), 2), 2, null);

            if ($chave === 'ts') {
                $ts = $valor;
            } elseif ($chave === 'v1') {
                $v1 = $valor;
            }
        }

        return [$ts, $v1];
    }
}
