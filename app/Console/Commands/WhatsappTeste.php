<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Envio MANUAL de uma mensagem de WhatsApp de teste (WhatsApp Fatia 1, D75) — prova
 * o caminho ponta a ponta sem automação/gatilho (isso é a Fatia 3). Usa a instância
 * DESTE tenant (token da instância). Gated pelo recurso `whatsapp`. Sem segredos no log.
 */
class WhatsappTeste extends Command
{
    protected $signature = 'nextgest:whatsapp-teste {tenant : id/slug do tenant} {numero : número com DDD (DDI 55 opcional)} {--mensagem=}';

    protected $description = 'Envia uma mensagem de WhatsApp de teste pela instância do tenant';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));

        if (! $tenant instanceof Tenant) {
            $this->error('Tenant não encontrado.');

            return self::FAILURE;
        }

        return $tenant->run(function () use ($tenant): int {
            if (! $tenant->temRecurso('whatsapp')) {
                $this->error('Recurso "whatsapp" está desligado para este estabelecimento.');

                return self::FAILURE;
            }

            $mensagem = (string) ($this->option('mensagem') ?: 'Mensagem de teste do Nextgest ✅');

            try {
                $r = app(WhatsAppService::class)->enviarTexto((string) $this->argument('numero'), $mensagem);
            } catch (WhatsAppException $e) {
                $this->error('Falha: '.$e->getMessage());

                return self::FAILURE;
            }

            $this->info('Enviado. id='.($r['id'] ?? '?').' status='.($r['status'] ?? '?'));

            return self::SUCCESS;
        });
    }
}
