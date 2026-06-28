<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutomacaoWhatsapp;
use App\Models\Agendamento;
use App\Models\PedidoAvaliacao;
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
use Illuminate\Support\Facades\URL;

/**
 * Envia UM pedido de avaliação pós-serviço por WhatsApp (Fatia 5, D81) — uma mensagem com
 * LINK ASSINADO para a avaliação daquele atendimento (tela D51, sem login). Enfileirado
 * pelo comando `nextgest:enviar-avaliacoes` (espaçado, anti-ban). `tries=1` (anti-storm).
 * Reusa o envio (D75) e o template (D77). NÃO recebe resposta no WhatsApp.
 */
class EnviarAvaliacaoWhatsApp implements ShouldQueue
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
            $rec = PedidoAvaliacao::query()->where('agendamento_id', $this->agendamentoId)->first();
            if (! $rec || $rec->status === PedidoAvaliacao::ENVIADO) {
                return;
            }

            $ag = Agendamento::with(['cliente', 'itens.servico', 'profissional'])->find($this->agendamentoId);

            $cfg = WhatsappConfig::query()->first();
            $aut = $cfg?->automacoes['avaliacao_pos_servico'] ?? [];

            $invalido = ! $ag
                || $ag->status !== 'concluido'
                || ! $ag->cliente
                || $ag->cliente->whatsapp_optout
                || blank($ag->cliente->telefone)
                || $ag->avaliacao()->exists()        // já avaliado → não pede de novo
                || ! ($aut['ativo'] ?? false);

            if ($invalido) {
                $rec->update(['status' => PedidoAvaliacao::FALHOU]);

                return;
            }

            // Link ASSINADO (HMAC + expira) p/ a avaliação daquele atendimento — sem login,
            // não-adivinhável, sem dado pessoal na URL. Anonimato do D51 preservado.
            $link = URL::temporarySignedRoute(
                'tenant.avaliar',
                now()->addDays((int) config('whatsapp.avaliacao.link_validade_dias', 7)),
                ['tenant' => tenant('id'), 'agendamento' => $ag->id],
            );

            $texto = RenderizadorTemplate::render(
                (string) ($aut['template'] ?? AutomacaoWhatsapp::AvaliacaoPosServico->templatePadrao()),
                [
                    'cliente' => $ag->cliente->nome,
                    'servico' => $ag->itens->first()?->servico?->nome ?? 'seu atendimento',
                    'profissional' => $ag->profissional?->name ?? '',
                    'link' => $link,
                    'salao' => (string) (tenant('nome') ?? ''),
                ],
            );

            try {
                app(WhatsAppService::class)->enviarTexto((string) $ag->cliente->telefone, $texto);
                $rec->update(['status' => PedidoAvaliacao::ENVIADO, 'enviado_em' => now()]);
            } catch (WhatsAppException) {
                $rec->update(['status' => PedidoAvaliacao::FALHOU]);
            }
        });
    }
}
