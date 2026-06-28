<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EnviarLembreteWhatsApp;
use App\Models\Agendamento;
use App\Models\LembreteServico;
use App\Models\Tenant;
use App\Models\WhatsappConfig;
use App\Services\WhatsApp\Aquecimento;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Lembrete de serviço por WhatsApp (Fatia 4, D79). Roda a cada minuto (scheduler).
 * Por tenant com a automação `lembrete_servico` LIGADA e o WhatsApp CONECTADO: acha os
 * agendamentos "a atender" que entram na janela (now → now+antecedência, fuso APP_TIMEZONE),
 * de clientes NÃO opt-out e ainda não avisados, e ENFILEIRA o envio — espaçado e dentro
 * dos tetos (anti-ban). SÓ LÊ a agenda (não toca o MotorDisponibilidade).
 *
 * Idempotência: `lembretes_servico.agendamento_id` é único → um lembrete por agendamento;
 * re-run/remarcação não duplica. WhatsApp caído → não enfileira (vencidos na queda somem da
 * janela, não acumulam). Excedente do minuto fica para o próximo minuto (a janela segura).
 */
class EnviarLembretes extends Command
{
    protected $signature = 'nextgest:enviar-lembretes';

    protected $description = 'Enfileira lembretes de serviço por WhatsApp (anti-ban, idempotente)';

    public function handle(): int
    {
        $limiteMin = (int) config('whatsapp.lembretes.limite_por_minuto', 4);
        $intervalo = (int) config('whatsapp.lembretes.intervalo_segundos', 15);
        $antecedenciaPadrao = (int) config('whatsapp.lembretes.antecedencia_min_padrao', 120);

        $totalEnfileirados = 0;

        foreach (Tenant::all() as $tenant) {
            $enfileirados = $tenant->run(function () use ($tenant, $limiteMin, $intervalo, $antecedenciaPadrao): int {
                if (! $tenant->temRecurso('whatsapp')) {
                    return 0;
                }

                $cfg = WhatsappConfig::query()->first();
                $aut = $cfg?->automacoes['lembrete_servico'] ?? null;

                if (! $cfg || ! ($aut['ativo'] ?? false) || blank($cfg->instancia)) {
                    return 0; // automação off ou sem instância
                }

                // WhatsApp caído → não enfileira (evita acúmulo p/ estourar na reconexão).
                try {
                    if (app(WhatsAppService::class)->status() !== 'open') {
                        return 0;
                    }
                } catch (WhatsAppException) {
                    return 0;
                }

                // Teto efetivo do dia = min(normal, aquecimento), consumo COMBINADO
                // (lembrete + avaliação) — Modo Aquecimento (D82) por cima das travas D79.
                $restanteDia = app(Aquecimento::class)->restanteHoje($cfg);
                if ($restanteDia <= 0) {
                    return 0;
                }

                $lote = min($limiteMin, $restanteDia);
                $agora = now();
                $enfileirados = 0;

                // 1) Represados pela JANELA de horário (D83) que já venceram — re-despacha
                //    primeiro (esperaram). A janela é reconferida no job ao enviar.
                $represados = LembreteServico::query()
                    ->where('status', LembreteServico::ENFILEIRADO)
                    ->whereNotNull('agendado_para')
                    ->where('agendado_para', '<=', $agora)
                    ->orderBy('agendado_para')
                    ->limit($lote)
                    ->get();

                foreach ($represados as $rec) {
                    $rec->update(['agendado_para' => null]); // reclama o slot (não re-pega no próximo minuto)
                    EnviarLembreteWhatsApp::dispatch($tenant->getKey(), $rec->agendamento_id)
                        ->delay($agora->copy()->addSeconds($enfileirados * $intervalo));
                    $enfileirados++;
                }

                // 2) Novos elegíveis (até completar o lote).
                if ($enfileirados < $lote) {
                    $antecedencia = (int) ($aut['antecedencia_min'] ?? $antecedenciaPadrao);

                    $elegiveis = Agendamento::query()
                        ->whereNotIn('status', ['concluido', 'cancelado', 'nao_compareceu'])
                        ->whereBetween('data_hora_inicio', [$agora, $agora->copy()->addMinutes($antecedencia)])
                        ->whereDoesntHave('lembreteServico') // ainda não avisado (idempotência)
                        ->whereHas('cliente', fn ($c) => $c->where('whatsapp_optout', false)->whereNotNull('telefone')->where('telefone', '!=', ''))
                        ->orderBy('data_hora_inicio')
                        ->limit($lote - $enfileirados)
                        ->get();

                    foreach ($elegiveis as $ag) {
                        // Cria o registro (agendamento_id único) — guarda contra corrida/duplo.
                        $rec = LembreteServico::firstOrCreate(
                            ['agendamento_id' => $ag->id],
                            ['status' => LembreteServico::ENFILEIRADO, 'enfileirado_em' => now()],
                        );

                        if (! $rec->wasRecentlyCreated) {
                            continue; // já existia
                        }

                        // Espaçamento intra-minuto (vale com fila assíncrona; no-op em sync).
                        EnviarLembreteWhatsApp::dispatch($tenant->getKey(), $ag->id)
                            ->delay($agora->copy()->addSeconds($enfileirados * $intervalo));

                        $enfileirados++;
                    }
                }

                return $enfileirados;
            });

            $totalEnfileirados += $enfileirados;
        }

        $this->info("Lembretes enfileirados: {$totalEnfileirados}");

        return self::SUCCESS;
    }
}
