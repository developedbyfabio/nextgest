<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutomacaoWhatsapp;
use App\Models\Agendamento;
use App\Models\LembreteServico;
use App\Models\Tenant;
use App\Models\WhatsappConfig;
use App\Services\WhatsApp\RenderizadorTemplate;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Envia UM lembrete de serviço por WhatsApp (Fatia 4, D79). Enfileirado pelo comando
 * `nextgest:enviar-lembretes` (espaçado, anti-ban). Roda fora do contexto de tenant →
 * reinicializa a tenancy pelo `tenantId`. REVALIDA antes de enviar (status/futuro/
 * opt-out/automação) e marca o registro como enviado/falhou. `tries=1`: nunca retransmite
 * em rajada (anti-ban) — uma falha vira `falhou`, não reenvio.
 */
class EnviarLembreteWhatsApp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public string $tenantId, public int $agendamentoId) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant instanceof Tenant) {
            return;
        }

        $tenant->run(function () {
            $rec = LembreteServico::query()->where('agendamento_id', $this->agendamentoId)->first();
            if (! $rec || $rec->status === LembreteServico::ENVIADO) {
                return; // idempotência: já enviado / sem registro
            }

            $ag = Agendamento::with(['cliente', 'itens.servico', 'profissional'])->find($this->agendamentoId);

            $cfg = WhatsappConfig::query()->first();
            $aut = $cfg?->automacoes['lembrete_servico'] ?? [];

            $invalido = ! $ag
                || in_array($ag->status, ['concluido', 'cancelado', 'nao_compareceu'], true)
                || $ag->data_hora_inicio->isPast()
                || ! $ag->cliente
                || $ag->cliente->whatsapp_optout
                || blank($ag->cliente->telefone)
                || ! ($aut['ativo'] ?? false);

            if ($invalido) {
                $rec->update(['status' => LembreteServico::FALHOU]);

                return;
            }

            $texto = RenderizadorTemplate::render(
                (string) ($aut['template'] ?? AutomacaoWhatsapp::LembreteServico->templatePadrao()),
                [
                    'cliente' => $ag->cliente->nome,
                    'data' => $ag->data_hora_inicio->format('d/m/Y'),
                    'hora' => $ag->data_hora_inicio->format('H:i'),
                    'servico' => $ag->itens->first()?->servico?->nome ?? 'serviço',
                    'profissional' => $ag->profissional?->name ?? '',
                    'salao' => (string) (tenant('nome') ?? ''),
                ],
            );

            try {
                app(WhatsAppService::class)->enviarTexto((string) $ag->cliente->telefone, $texto);
                $rec->update(['status' => LembreteServico::ENVIADO, 'enviado_em' => now()]);
            } catch (WhatsAppException) {
                $rec->update(['status' => LembreteServico::FALHOU]); // sem retry (anti-ban)
            }
        });
    }
}
