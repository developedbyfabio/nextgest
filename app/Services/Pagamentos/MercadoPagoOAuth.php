<?php

declare(strict_types=1);

namespace App\Services\Pagamentos;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente OAuth do Mercado Pago (Modelo A, D78). SÓ a conexão da conta do salão:
 * monta a URL de autorização (com `state`), troca o `code` pelo token e lê os dados
 * PÚBLICOS da conta. Não cobra nada (isso é o adapter de cobrança, G2).
 *
 * SEGREDO: client_id/client_secret só de config('pagamentos.mercadopago.*') (.env).
 * Nunca logar token/secret. Falha/timeout → PagamentoGatewayException.
 */
class MercadoPagoOAuth
{
    /** URL para o dono autorizar no site do MP (carrega o `state` anti-CSRF). */
    public function urlAutorizacao(string $state): string
    {
        $clientId = (string) config('pagamentos.mercadopago.client_id');
        $redirect = (string) config('pagamentos.mercadopago.redirect_uri');

        if ($clientId === '' || $redirect === '') {
            throw new PagamentoGatewayException('Conexão com o Mercado Pago ainda não configurada (credenciais OAuth pendentes).');
        }

        $query = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'platform_id' => 'mp',
            'state' => $state,
            'redirect_uri' => $redirect,
        ]);

        return rtrim((string) config('pagamentos.mercadopago.auth_url'), '?').'?'.$query;
    }

    /**
     * Troca o `code` do callback pelo token da conta do salão.
     *
     * @return array{access_token: ?string, refresh_token: ?string, user_id: mixed, public_key: ?string, expires_in: ?int, scope: ?string}
     */
    public function trocarCodigo(string $code): array
    {
        $resp = $this->req(fn () => Http::asForm()->post((string) config('pagamentos.mercadopago.token_url'), [
            'client_id' => (string) config('pagamentos.mercadopago.client_id'),
            'client_secret' => (string) config('pagamentos.mercadopago.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => (string) config('pagamentos.mercadopago.redirect_uri'),
        ]), 'trocar código');

        return $resp->json();
    }

    /**
     * Dados PÚBLICOS da conta conectada (id/apelido/e-mail) para exibir. Tolera
     * falha (devolve [] — a tela usa um rótulo de fallback).
     *
     * @return array<string, mixed>
     */
    public function contaInfo(string $accessToken): array
    {
        if ($accessToken === '') {
            return [];
        }

        try {
            $resp = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout((int) config('pagamentos.mercadopago.timeout', 15))
                ->get(rtrim((string) config('pagamentos.mercadopago.api_url'), '/').'/users/me');
        } catch (ConnectionException) {
            return [];
        }

        return $resp->successful() ? (array) $resp->json() : [];
    }

    private function req(callable $call, string $acao)
    {
        try {
            $resp = $call();
        } catch (ConnectionException) {
            Log::warning('MP OAuth: sem conexão.', ['acao' => $acao]);

            throw new PagamentoGatewayException('Não foi possível falar com o Mercado Pago agora. Tente novamente.');
        }

        if ($resp->failed()) {
            Log::warning('MP OAuth: chamada falhou.', ['acao' => $acao, 'http_status' => $resp->status()]);

            throw new PagamentoGatewayException('O Mercado Pago recusou a autorização. Tente conectar novamente.');
        }

        return $resp;
    }
}
