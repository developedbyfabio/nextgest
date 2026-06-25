<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

use App\Models\Assinatura;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client da API de Assinaturas (Preapproval) do Mercado Pago — adesão recorrente
 * SaaS (salão → Nextgest), D61. Fluxo "pago pendente": cria com status `pending` e
 * SEM card_token_id → o MP devolve um `init_point` (página hospedada) onde o dono
 * cadastra o cartão e autoriza. A confirmação das cobranças é por webhook (5b).
 *
 * SEGREDO: o token vem só de config('mercadopago.access_token') (.env). Nunca é
 * logado nem exposto. Em falha, loga apenas status HTTP + mensagem do MP.
 */
class PreapprovalClient
{
    /**
     * Cria a recorrência no MP para uma assinatura. Mensal, valor do plano, 1ª
     * cobrança no fim do trial (start_date). Devolve [id, init_point, status, raw].
     *
     * @throws MercadoPagoException
     */
    public function criarPreapproval(Assinatura $assinatura, string $payerEmail): array
    {
        $valor = (float) $assinatura->valor_mensal;

        if ($valor <= 0) {
            throw new MercadoPagoException('Defina um valor mensal maior que zero antes de ativar a cobrança automática.');
        }

        // 1ª cobrança no fim do trial; se já passou, começa agora (com folga p/ o MP).
        $inicio = $assinatura->primeiraCobranca();
        $start = $inicio->isFuture() ? $inicio : now()->addMinutes(10);

        $nome = $assinatura->tenant?->nome ?? $assinatura->tenant_id;

        $payload = [
            'reason' => 'Assinatura Nextgest — '.$nome,
            'external_reference' => (string) $assinatura->tenant_id,
            'payer_email' => $payerEmail,
            'back_url' => (string) config('mercadopago.back_url'),
            'status' => 'pending', // sem card_token_id → MP devolve init_point
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => round($valor, 2),
                'currency_id' => 'BRL',
                // MP exige milissegundos + offset (ex.: 2026-06-25T18:35:00.000Z). UTC + 'Z'.
                'start_date' => $start->clone()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            ],
        ];

        $resposta = $this->http()->post('/preapproval', $payload);

        if ($resposta->failed()) {
            Log::warning('Mercado Pago: falha ao criar preapproval.', [
                'tenant' => $assinatura->tenant_id,
                'http_status' => $resposta->status(),
                'mp_message' => $resposta->json('message') ?? $resposta->json('error'),
            ]);

            throw new MercadoPagoException('Não foi possível criar a cobrança automática no Mercado Pago. Tente novamente.');
        }

        $json = $resposta->json();

        return [
            'id' => $json['id'] ?? null,
            'init_point' => $json['init_point'] ?? ($json['sandbox_init_point'] ?? null),
            'status' => $json['status'] ?? null,
            'raw' => $json,
        ];
    }

    /**
     * Consulta o estado atual da recorrência no MP.
     *
     * @throws MercadoPagoException
     */
    public function consultar(string $preapprovalId): array
    {
        $resposta = $this->http()->get("/preapproval/{$preapprovalId}");

        if ($resposta->failed()) {
            Log::warning('Mercado Pago: falha ao consultar preapproval.', [
                'http_status' => $resposta->status(),
            ]);

            throw new MercadoPagoException('Não foi possível consultar a cobrança no Mercado Pago.');
        }

        return $resposta->json();
    }

    /** Cliente HTTP autenticado (token via config; nunca logado). */
    private function http(): PendingRequest
    {
        $token = (string) config('mercadopago.access_token');

        if ($token === '') {
            throw new MercadoPagoException('Integração com o Mercado Pago não configurada.');
        }

        return Http::baseUrl((string) config('mercadopago.base_url'))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('mercadopago.timeout', 15));
    }
}
