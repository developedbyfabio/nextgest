<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Services\MercadoPago\MercadoPagoException;
use App\Services\MercadoPago\ProcessadorWebhook;
use App\Services\MercadoPago\ValidadorWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de pagamentos (D62). Rota PÚBLICA `POST /webhooks/pagamentos/{gateway}`.
 *
 * mercadopago: valida a assinatura (x-signature) ANTES de tudo; inválida → 401 (não
 * processa). Válida → consulta o recurso na API e espelha o estado (dedupe no
 * ProcessadorWebhook). Evento válido (mesmo duplicado) → 200. Falha transitória ao
 * consultar a API → 500 para o MP REENVIAR (a reconciliação agendada é a rede de
 * segurança). Outros gateways: stub 200 (compat).
 */
class WebhookPagamentoController
{
    public function __construct(
        private ValidadorWebhook $validador,
        private ProcessadorWebhook $processador,
    ) {}

    public function handle(Request $request, string $gateway): JsonResponse
    {
        if ($gateway !== 'mercadopago') {
            return response()->json(['received' => true]); // stub p/ outros gateways
        }

        // Observabilidade: só FATOS (tipo/recurso/modo), nunca headers crus nem segredo.
        $tipo = (string) ($request->input('type') ?? $request->query('type') ?? '');
        $dataId = $this->validador->dataId($request);

        Log::info('Webhook MP: recebido', [
            'tipo' => $tipo,
            'action' => $request->input('action'),
            'data_id' => $dataId,
            'live_mode' => $request->input('live_mode'),
        ]);

        // SEGURANÇA: assinatura inválida/ausente → rejeita sem processar.
        if (! $this->validador->valido($request)) {
            Log::warning('Webhook MP: assinatura inválida → 401 (não processa)', ['tipo' => $tipo, 'data_id' => $dataId]);

            return response()->json(['error' => 'assinatura inválida'], 401);
        }

        Log::info('Webhook MP: assinatura válida', ['tipo' => $tipo, 'data_id' => $dataId]);

        if ($dataId === '') {
            Log::info('Webhook MP: sem data.id → ignorado (ack)', ['tipo' => $tipo]);

            return response()->json(['ignored' => true]); // nada a fazer (ack)
        }

        try {
            $this->processador->processar($tipo, $dataId);
        } catch (MercadoPagoException) {
            // Falha ao consultar a API → não confirma; deixa o MP reenviar.
            Log::warning('Webhook MP: falha ao consultar a API → 500 (MP reenvia)', ['tipo' => $tipo, 'data_id' => $dataId]);

            return response()->json(['retry' => true], 500);
        }

        return response()->json(['received' => true]);
    }
}
