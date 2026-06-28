<?php

declare(strict_types=1);

namespace App\Livewire\Painel;

use App\Models\WhatsappConfig;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Aviso global "WhatsApp caiu, reconecte" no topo do painel (Fatia 4.5, D80). Embutido
 * no layout (gateado por `can('gerenciar_whatsapp')`). Confere o estado REAL por
 * `wire:init` (não bloqueia o 1º render). Reusa o status ao vivo do D76.
 *
 * Regra (condicional, p/ não poluir): só mostra quando tem recurso `whatsapp` + JÁ
 * conectou alguma vez (`instancia` setada) + status agora ≠ `open`. Evolution fora do ar
 * (erro) → NÃO alarma (é infra, não algo que o dono reconecta). Sem polling contínuo.
 */
class AvisoWhatsappConexao extends Component
{
    public bool $caiu = false;

    public function verificar(): void
    {
        if (! auth('web')->user()?->can('gerenciar_whatsapp') || ! tenant_tem_recurso('whatsapp')) {
            return;
        }

        $cfg = WhatsappConfig::query()->first();
        if (! $cfg || blank($cfg->instancia)) {
            return; // nunca conectou → nada a avisar
        }

        try {
            $this->caiu = app(WhatsAppService::class)->status() !== 'open';
        } catch (WhatsAppException) {
            $this->caiu = false; // Evolution indisponível: não é "queda da sessão"
        }
    }

    public function render(): View
    {
        return view('livewire.painel.aviso-whatsapp-conexao');
    }
}
