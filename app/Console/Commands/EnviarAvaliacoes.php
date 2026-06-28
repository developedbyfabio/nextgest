<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EnviarAvaliacaoWhatsApp;
use App\Models\Agendamento;
use App\Models\PedidoAvaliacao;
use App\Models\Tenant;
use App\Models\WhatsappConfig;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Pedido de avaliação pós-serviço por WhatsApp (Fatia 5, D81). Roda a cada minuto.
 * Por tenant com `avaliacao_pos_servico` LIGADA, **termo aceito (D80)** e WhatsApp
 * CONECTADO: acha atendimentos CONCLUÍDOS que terminaram há ~X min (janela, fuso
 * APP_TIMEZONE), ainda NÃO avaliados e NÃO pedidos, de clientes não opt-out, e ENFILEIRA
 * o envio do link — espaçado e dentro dos tetos (reusa os freios anti-ban do D79).
 *
 * SÓ LÊ a agenda (não toca o motor). Idempotente (`pedidos_avaliacao.agendamento_id`
 * único). A janela tem limite inferior (buffer) p/ não inundar atendimentos antigos.
 */
class EnviarAvaliacoes extends Command
{
    protected $signature = 'nextgest:enviar-avaliacoes';

    protected $description = 'Enfileira pedidos de avaliação pós-serviço por WhatsApp (anti-ban, idempotente)';

    public function handle(): int
    {
        $limiteMin = (int) config('whatsapp.lembretes.limite_por_minuto', 4);
        $limiteDia = (int) config('whatsapp.lembretes.limite_por_dia', 150);
        $intervalo = (int) config('whatsapp.lembretes.intervalo_segundos', 15);
        $aposPadrao = (int) config('whatsapp.avaliacao.apos_min_padrao', 120);
        $buffer = (int) config('whatsapp.avaliacao.janela_buffer_min', 60);

        $total = 0;

        foreach (Tenant::all() as $tenant) {
            $total += $tenant->run(function () use ($tenant, $limiteMin, $limiteDia, $intervalo, $aposPadrao, $buffer): int {
                if (! $tenant->temRecurso('whatsapp')) {
                    return 0;
                }

                $cfg = WhatsappConfig::query()->first();
                $aut = $cfg?->automacoes['avaliacao_pos_servico'] ?? null;

                // Automação on + termo aceito (D80) + instância configurada.
                if (! $cfg || ! ($aut['ativo'] ?? false) || ! $cfg->termoAceito() || blank($cfg->instancia)) {
                    return 0;
                }

                try {
                    if (app(WhatsAppService::class)->status() !== 'open') {
                        return 0; // caído → não enfileira (não acumula)
                    }
                } catch (WhatsAppException) {
                    return 0;
                }

                $hoje = PedidoAvaliacao::query()->whereDate('enfileirado_em', today())->count();
                $restanteDia = $limiteDia - $hoje;
                if ($restanteDia <= 0) {
                    return 0;
                }

                $lote = min($limiteMin, $restanteDia);
                $apos = (int) ($aut['apos_min'] ?? $aposPadrao);
                $ate = now()->copy()->subMinutes($apos);
                $de = $ate->copy()->subMinutes($buffer);

                $elegiveis = Agendamento::query()
                    ->where('status', 'concluido')
                    ->whereBetween('data_hora_fim', [$de, $ate])
                    ->whereDoesntHave('pedidoAvaliacao') // ainda não pedido (idempotência)
                    ->whereDoesntHave('avaliacao')       // ainda não avaliado (D51)
                    ->whereHas('cliente', fn ($c) => $c->where('whatsapp_optout', false)->whereNotNull('telefone')->where('telefone', '!=', ''))
                    ->orderBy('data_hora_fim')
                    ->limit($lote)
                    ->get();

                $i = 0;
                foreach ($elegiveis as $ag) {
                    $rec = PedidoAvaliacao::firstOrCreate(
                        ['agendamento_id' => $ag->id],
                        ['status' => PedidoAvaliacao::ENFILEIRADO, 'enfileirado_em' => now()],
                    );

                    if (! $rec->wasRecentlyCreated) {
                        continue;
                    }

                    EnviarAvaliacaoWhatsApp::dispatch($tenant->getKey(), $ag->id)
                        ->delay(now()->copy()->addSeconds($i * $intervalo));

                    $i++;
                }

                return $i;
            });
        }

        $this->info("Pedidos de avaliação enfileirados: {$total}");

        return self::SUCCESS;
    }
}
