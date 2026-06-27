<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Conecta o WhatsApp de um tenant (WhatsApp Fatia 1, D75) — helper de CLI para a
 * validação ponta a ponta (a tela de QR é a Fatia 2). Cria/garante a instância do
 * salão na Evolution, persiste nome+token no banco do tenant e salva o QR como PNG
 * para o dono escanear (WhatsApp → Aparelhos conectados).
 *
 * Gated pelo recurso `whatsapp` (igual à UI). Nunca imprime segredos.
 */
class WhatsappConectar extends Command
{
    protected $signature = 'nextgest:whatsapp-conectar {tenant : id/slug do tenant}';

    protected $description = 'Cria/garante a instância de WhatsApp do tenant e gera o QR (PNG) para conectar';

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

            try {
                $res = app(WhatsAppService::class)->conectarInstancia();
            } catch (WhatsAppException $e) {
                $this->error('Falha: '.$e->getMessage());

                return self::FAILURE;
            }

            $this->info('Instância: '.$res['instancia']);

            $b64 = $res['qr_base64'] ?? null;
            if ($b64) {
                $png = base64_decode((string) preg_replace('#^data:image/\w+;base64,#', '', $b64), true);
                if ($png !== false) {
                    $path = storage_path('app/whatsapp-qr-'.tenant('id').'.png');
                    if (! is_dir(dirname($path))) {
                        mkdir(dirname($path), 0775, true);
                    }
                    file_put_contents($path, $png);
                    $this->info('QR salvo em: '.$path);
                    $this->line('Abra o WhatsApp → Aparelhos conectados → Conectar aparelho e escaneie o PNG.');

                    return self::SUCCESS;
                }
            }

            if (! empty($res['qr_code'])) {
                $this->warn('Sem imagem base64; código do QR (string) disponível no retorno da Evolution.');

                return self::SUCCESS;
            }

            $this->warn('Instância pronta, mas nenhum QR retornado (talvez já esteja conectada). Veja: nextgest:whatsapp-status.');

            return self::SUCCESS;
        });
    }
}
