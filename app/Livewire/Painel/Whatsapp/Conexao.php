<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Whatsapp;

use App\Models\WhatsappConfig;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Conexão do WhatsApp do salão (WhatsApp Fatia 2, D76). Item próprio do menu, gated
 * por recurso `whatsapp` + permissão `gerenciar_whatsapp`. Reusa o WhatsAppService
 * (D75): conecta (gera/renova QR), checa status AO VIVO e desconecta. Não envia
 * mensagem (Fatia 1) nem automatiza (Fatia 3).
 *
 * Estados: desconectado | aguardando | conectado | caiu | erro.
 * - `wire:init=sincronizar`: ao abrir, confirma o estado REAL na Evolution (sem
 *   bloquear o 1º render nem quebrar se a Evolution estiver fora).
 * - `aguardando`: QR na tela + `wire:poll` curto (verificarStatus) que PARA ao conectar.
 * - `conectado`: `wire:poll` lento (monitorar) detecta queda da sessão → `caiu`.
 * O QR expira em ~1 min → botão "Gerar novo QR". Nada de token/key na tela ou no log.
 */
#[Layout('components.layouts.painel')]
#[Title('WhatsApp')]
class Conexao extends Component
{
    public string $estado = 'desconectado';

    /** QR em base64 (data URI) enquanto aguarda leitura; nunca um segredo. */
    public ?string $qr = null;

    public ?string $instancia = null;

    public ?string $erro = null;

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $c = WhatsappConfig::query()->first();
        $this->instancia = $c?->instancia;
        $this->estado = $c?->status_conexao === 'open' ? 'conectado' : 'desconectado';
    }

    /** Confirma o estado real na Evolution ao abrir a tela (wire:init). */
    public function sincronizar(): void
    {
        if (blank($this->instancia)) {
            return;
        }

        try {
            $estadoReal = app(WhatsAppService::class)->status();
        } catch (WhatsAppException) {
            return; // Evolution fora → mantém o estado armazenado, sem quebrar a tela
        }

        $this->estado = $estadoReal === 'open'
            ? 'conectado'
            : ($this->estado === 'conectado' ? 'caiu' : 'desconectado');
    }

    /** Conecta: cria/garante a instância e mostra o QR para parear. */
    public function conectar(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        try {
            $res = app(WhatsAppService::class)->conectarInstancia();
            $this->instancia = $res['instancia'];
            $this->qr = $res['qr_base64'];
            $this->estado = $this->qr ? 'aguardando' : 'conectado';
            $this->erro = null;
        } catch (WhatsAppException $e) {
            $this->estado = 'erro';
            $this->erro = $e->getMessage();
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }

    /** Gera um QR novo (o anterior expira em ~1 min). */
    public function renovarQr(): void
    {
        $this->conectar();
    }

    /** Poll curto enquanto aguarda leitura: detecta a conexão sem recarregar. */
    public function verificarStatus(): void
    {
        if ($this->estado !== 'aguardando') {
            return; // só checa enquanto espera o pareamento
        }

        try {
            if (app(WhatsAppService::class)->status() === 'open') {
                $this->estado = 'conectado';
                $this->qr = null;
                Flux::toast('WhatsApp conectado!', variant: 'success');
            }
        } catch (WhatsAppException) {
            // Transitório: mantém aguardando, não derruba a tela.
        }
    }

    /** Poll lento enquanto conectado: detecta queda da sessão e avisa. */
    public function monitorar(): void
    {
        if ($this->estado !== 'conectado') {
            return;
        }

        try {
            if (app(WhatsAppService::class)->status() !== 'open') {
                $this->estado = 'caiu';
                Flux::toast('A conexão do WhatsApp caiu. Reconecte.', variant: 'warning');
            }
        } catch (WhatsAppException) {
            // Falha transitória ao consultar → não marca queda (evita falso alarme).
        }
    }

    public function desconectar(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        try {
            app(WhatsAppService::class)->desconectar();
        } catch (WhatsAppException) {
            // Mesmo se a Evolution falhar, refletimos desconectado na tela.
        }

        $this->estado = 'desconectado';
        $this->qr = null;
    }

    public function render(): View
    {
        return view('livewire.painel.whatsapp.conexao');
    }
}
