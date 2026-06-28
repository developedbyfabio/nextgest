<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EnviarLembreteWhatsApp;
use App\Models\Agendamento;
use App\Models\LembreteServico;
use App\Models\Tenant;
use App\Models\WhatsappConfig;
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
        $limiteDia = (int) config('whatsapp.lembretes.limite_por_dia', 150);
        $intervalo = (int) config('whatsapp.lembretes.intervalo_segundos', 15);
        $antecedenciaPadrao = (int) config('whatsapp.lembretes.antecedencia_min_padrao', 120);

        $totalEnfileirados = 0;

        foreach (Tenant::all() as $tenant) {
            $enfileirados = $tenant->run(function () use ($tenant, $limiteMin, $limiteDia, $intervalo, $antecedenciaPadrao): int {
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

                // Teto diário (por tenant): conta o que já foi enfileirado hoje.
                $hoje = LembreteServico::query()->whereDate('enfileirado_em', today())->count();
                $restanteDia = $limiteDia - $hoje;
                if ($restanteDia <= 0) {
                    return 0;
                }

                $lote = min($limiteMin, $restanteDia);
                $antecedencia = (int) ($aut['antecedencia_min'] ?? $antecedenciaPadrao);
                $agora = now();

                $elegiveis = Agendamento::query()
                    ->whereNotIn('status', ['concluido', 'cancelado', 'nao_compareceu'])
                    ->whereBetween('data_hora_inicio', [$agora, $agora->copy()->addMinutes($antecedencia)])
                    ->whereDoesntHave('lembreteServico') // ainda não avisado (idempotência)
                    ->whereHas('cliente', fn ($c) => $c->where('whatsapp_optout', false)->whereNotNull('telefone')->where('telefone', '!=', ''))
                    ->orderBy('data_hora_inicio')
                    ->limit($lote)
                    ->get();

                $i = 0;
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
                        ->delay($agora->copy()->addSeconds($i * $intervalo));

                    $i++;
                }

                return $i;
            });

            $totalEnfileirados += $enfileirados;
        }

        $this->info("Lembretes enfileirados: {$totalEnfileirados}");

        return self::SUCCESS;
    }
}
