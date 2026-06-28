<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MensagemWhatsapp;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Expurgo do CONTEÚDO do log de mensagens de WhatsApp (Controle de mensagens, D83).
 * Roda diário (scheduler). Após o prazo `config('whatsapp.historico.expurgo_dias')`,
 * apaga o TEXTO da mensagem (LGPD/minimização) mantendo os METADADOS (quem/quando/status).
 * `expurgo_dias = 0` → expurgo desligado. UPDATE sempre com WHERE (prazo + conteúdo != null).
 */
class ExpurgarConteudoWhatsApp extends Command
{
    protected $signature = 'nextgest:whatsapp-expurgar-conteudo';

    protected $description = 'Apaga o conteúdo antigo do log de WhatsApp, mantendo os metadados (LGPD)';

    public function handle(): int
    {
        $dias = (int) config('whatsapp.historico.expurgo_dias', 90);

        if ($dias <= 0) {
            $this->info('Expurgo desligado (expurgo_dias = 0).');

            return self::SUCCESS;
        }

        $limite = now()->copy()->subDays($dias);
        $total = 0;

        foreach (Tenant::all() as $tenant) {
            $total += $tenant->run(function () use ($limite): int {
                return MensagemWhatsapp::query()
                    ->whereNotNull('conteudo')
                    ->where('created_at', '<', $limite)
                    ->update(['conteudo' => null, 'conteudo_expurgado_em' => now()]);
            });
        }

        $this->info("Conteúdos expurgados (metadados mantidos): {$total}");

        return self::SUCCESS;
    }
}
