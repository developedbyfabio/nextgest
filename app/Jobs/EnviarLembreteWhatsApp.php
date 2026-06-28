<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutomacaoWhatsapp;
use App\Models\Agendamento;
use App\Models\LembreteServico;
use App\Models\MensagemWhatsapp;
use App\Models\Tenant;
use App\Models\WhatsappConfig;
use App\Services\WhatsApp\JanelaEnvio;
use App\Services\WhatsApp\RegistroMensagem;
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
                $rec->update(['status' => LembreteServico::FALHOU, 'agendado_para' => null]);
                RegistroMensagem::registrar([
                    'automacao' => 'lembrete_servico',
                    'agendamento_id' => $ag?->id,
                    'cliente_id' => $ag?->cliente?->id,
                    'telefone' => $ag?->cliente?->telefone,
                    'status' => MensagemWhatsapp::DESCARTADO,
                    'motivo' => 'condição mudou antes do envio',
                ]);

                return;
            }

            // Janela de horário (D83): decidida NO ENVIO (servidor). Fora da janela: se o
            // atendimento já teria começado no próximo horário válido → DESCARTA (lembrete
            // sem sentido); senão → ADIA (o comando re-despacha quando vencer). Fuso APP_TIMEZONE.
            $janela = app(JanelaEnvio::class)->paraAutomacao('lembrete_servico', $cfg);
            if (! app(JanelaEnvio::class)->aberta($janela)) {
                $proxima = app(JanelaEnvio::class)->proximaAbertura($janela);

                if ($ag->data_hora_inicio->lte($proxima)) {
                    $rec->update(['status' => LembreteServico::FALHOU, 'agendado_para' => null]);
                    RegistroMensagem::registrar([
                        'automacao' => 'lembrete_servico',
                        'agendamento_id' => $ag->id,
                        'cliente_id' => $ag->cliente->id,
                        'telefone' => $ag->cliente->telefone,
                        'status' => MensagemWhatsapp::DESCARTADO,
                        'motivo' => 'fora da janela: o atendimento já teria começado',
                    ]);

                    return;
                }

                $rec->update(['status' => LembreteServico::ENFILEIRADO, 'agendado_para' => $proxima]);

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
                $rec->update(['status' => LembreteServico::ENVIADO, 'agendado_para' => null, 'enviado_em' => now()]);
                RegistroMensagem::registrar([
                    'automacao' => 'lembrete_servico',
                    'agendamento_id' => $ag->id,
                    'cliente_id' => $ag->cliente->id,
                    'telefone' => $ag->cliente->telefone,
                    'status' => MensagemWhatsapp::ENVIADO,
                    'conteudo' => $texto,
                    'enviado_em' => now(),
                ]);
            } catch (WhatsAppException) {
                $rec->update(['status' => LembreteServico::FALHOU, 'agendado_para' => null]); // sem retry (anti-ban)
                RegistroMensagem::registrar([
                    'automacao' => 'lembrete_servico',
                    'agendamento_id' => $ag->id,
                    'cliente_id' => $ag->cliente->id,
                    'telefone' => $ag->cliente->telefone,
                    'status' => MensagemWhatsapp::FALHOU,
                    'motivo' => 'falha no envio',
                    'conteudo' => $texto,
                ]);
            }
        });
    }
}
