<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

use App\Models\Assinatura;
use App\Models\Fatura;
use App\Models\WebhookEvento;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Processa um evento de assinatura do Mercado Pago (D62). Sempre CONSULTA o recurso na
 * API (não confia no corpo) e ESPELHA o estado em `assinaturas`/`faturas`.
 *
 * Idempotente em duas camadas:
 *  - dedupe por `webhook_eventos` (chave por recurso/estado: o MP reenvia);
 *  - operações de dado idempotentes (`updateOrCreate` da fatura por competência).
 *
 * Regra (Fabio): aprovado → fatura paga + ativa; recusado → fatura vencida NA DATA DA
 * FALHA (situacaoAcesso conta os 20 dias dali → 4c suspende após o prazo).
 */
class ProcessadorWebhook
{
    private const GATEWAY = 'mercadopago';

    public function __construct(private PreapprovalClient $mp) {}

    /**
     * @throws MercadoPagoException quando a consulta à API falha (deixa o MP reenviar)
     */
    public function processar(string $tipo, string $dataId): void
    {
        if ($dataId === '') {
            return;
        }

        match ($tipo) {
            'subscription_authorized_payment' => $this->pagamento($dataId),
            'subscription_preapproval' => $this->preapproval($dataId),
            default => null, // tipo não tratado → ack sem efeito
        };
    }

    /** Cobrança mensal: consulta e espelha (paga / vencida na falha). */
    private function pagamento(string $authorizedPaymentId): void
    {
        $chave = 'authorized_payment:'.$authorizedPaymentId;

        if ($this->jaProcessado($chave)) {
            Log::info('Webhook MP: evento duplicado, ignorado', ['chave' => $chave]);

            return;
        }

        $pago = $this->mp->consultarPagamentoAutorizado($authorizedPaymentId);

        $assinatura = Assinatura::where('mp_preapproval_id', Arr::get($pago, 'preapproval_id'))->first();

        if (! $assinatura) {
            Log::warning('Webhook MP: assinatura não encontrada para o pagamento (ignorado)', [
                'preapproval_id' => Arr::get($pago, 'preapproval_id'),
                'authorized_payment_id' => $authorizedPaymentId,
            ]);
            $this->registrar($chave, 'subscription_authorized_payment'); // não é nossa: marca p/ não repetir

            return;
        }

        $statusPagamento = Arr::get($pago, 'payment.status');
        $referencia = (string) (Arr::get($pago, 'payment.id') ?? $authorizedPaymentId);
        $valor = (float) (Arr::get($pago, 'transaction_amount') ?? $assinatura->valor_mensal);
        $dataDebito = Carbon::parse(Arr::get($pago, 'debit_date') ?? Arr::get($pago, 'date_created') ?? now());
        $competencia = $dataDebito->copy()->startOfMonth();

        if ($statusPagamento === 'approved') {
            $assinatura->faturas()->updateOrCreate(
                ['competencia' => $competencia->toDateString()],
                [
                    'valor' => $valor,
                    'data_vencimento' => $dataDebito->toDateString(),
                    'status' => Fatura::PAGA,
                    'data_pagamento' => $dataDebito->toDateString(),
                    'forma_pagamento' => 'mercadopago',
                    'gateway_referencia' => $referencia,
                ],
            );

            $assinatura->update(['status' => Assinatura::ATIVA]);

            Log::info('Webhook MP: cobrança aprovada → fatura marcada paga', [
                'tenant' => $assinatura->tenant_id,
                'competencia' => $competencia->toDateString(),
                'gateway_referencia' => $referencia,
            ]);
        } else {
            // Recusada → fatura vencida NA DATA DA FALHA (hoje); dispara a carência (4c).
            $assinatura->faturas()->updateOrCreate(
                ['competencia' => $competencia->toDateString()],
                [
                    'valor' => $valor,
                    'data_vencimento' => now()->toDateString(),
                    'status' => Fatura::ABERTA,
                    'data_pagamento' => null,
                    'forma_pagamento' => 'mercadopago',
                    'gateway_referencia' => $referencia,
                ],
            );

            Log::info('Webhook MP: cobrança recusada → fatura vencida na data da falha', [
                'tenant' => $assinatura->tenant_id,
                'competencia' => $competencia->toDateString(),
                'status_pagamento' => $statusPagamento,
            ]);
        }

        $this->registrar($chave, 'subscription_authorized_payment');
    }

    /** Mudança de status da recorrência. */
    private function preapproval(string $preapprovalId): void
    {
        $pre = $this->mp->consultar($preapprovalId);
        $status = (string) (Arr::get($pre, 'status') ?? '');

        // Dedupe por id+status: retransmissão do MESMO status é ignorada; status novo processa.
        $chave = 'preapproval:'.$preapprovalId.':'.$status;

        if ($this->jaProcessado($chave)) {
            Log::info('Webhook MP: evento duplicado, ignorado', ['chave' => $chave]);

            return;
        }

        $assinatura = Assinatura::where('mp_preapproval_id', $preapprovalId)->first();

        if ($assinatura) {
            $assinatura->mp_status = $status;

            if ($status === 'authorized') {
                $assinatura->cobranca_automatica = true;
            } elseif ($status === 'cancelled') {
                $assinatura->status = Assinatura::CANCELADA; // bloqueia o painel (4c)
            }

            $assinatura->save();

            Log::info('Webhook MP: status da recorrência atualizado', [
                'tenant' => $assinatura->tenant_id,
                'mp_status' => $status,
            ]);
        } else {
            Log::warning('Webhook MP: assinatura não encontrada para a recorrência (ignorado)', [
                'preapproval_id' => $preapprovalId,
            ]);
        }

        $this->registrar($chave, 'subscription_preapproval');
    }

    private function jaProcessado(string $chave): bool
    {
        return WebhookEvento::where('gateway', self::GATEWAY)->where('evento_id', $chave)->exists();
    }

    private function registrar(string $chave, string $tipo): void
    {
        WebhookEvento::firstOrCreate(
            ['gateway' => self::GATEWAY, 'evento_id' => $chave],
            ['tipo' => $tipo, 'processado_em' => now()],
        );
    }
}
