<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Assinatura;
use App\Services\MercadoPago\MercadoPagoException;
use App\Services\MercadoPago\PreapprovalClient;
use App\Services\MercadoPago\ProcessadorWebhook;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

/**
 * Reconciliação das assinaturas recorrentes do Mercado Pago (D62) — rede de segurança
 * caso um webhook não chegue. Para cada assinatura com cobrança automática, consulta o
 * MP e reaplica via ProcessadorWebhook (mesmo dedupe → idempotente, só corrige).
 *
 * Roda 100% no central. Não confia no corpo (consulta a API). Agendado em console.php.
 */
class ReconciliarAssinaturas extends Command
{
    protected $signature = 'nextgest:reconciliar-assinaturas';

    protected $description = 'Sincroniza com o Mercado Pago as assinaturas com cobrança automática (idempotente)';

    public function handle(PreapprovalClient $mp, ProcessadorWebhook $processador): int
    {
        $assinaturas = Assinatura::where('cobranca_automatica', true)
            ->whereNotNull('mp_preapproval_id')
            ->get();

        $ok = 0;
        $erros = 0;

        foreach ($assinaturas as $assinatura) {
            try {
                // Status da recorrência.
                $processador->processar('subscription_preapproval', (string) $assinatura->mp_preapproval_id);

                // Cobranças que o webhook possa ter perdido.
                foreach ($mp->pagamentosDaAssinatura((string) $assinatura->mp_preapproval_id) as $pagamento) {
                    $id = (string) (Arr::get($pagamento, 'id') ?? '');
                    if ($id !== '') {
                        $processador->processar('subscription_authorized_payment', $id);
                    }
                }

                $ok++;
            } catch (MercadoPagoException $e) {
                $erros++;
                $this->warn("Falha ao reconciliar {$assinatura->tenant_id}: {$e->getMessage()}");
            }
        }

        $this->info("Reconciliação: {$ok} ok, {$erros} com falha (de {$assinaturas->count()}).");

        return self::SUCCESS;
    }
}
