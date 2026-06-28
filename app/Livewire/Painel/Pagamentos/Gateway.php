<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Pagamentos;

use App\Services\Pagamentos\ConexaoGatewayMercadoPago;
use App\Services\Pagamentos\PagamentoGatewayException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Gateway de pagamento do tenant — Modelo A (direto pro dono), Fatia G1 (D78). O dono
 * conecta a PRÓPRIA conta Mercado Pago via OAuth (o Nextgest nunca vê/digita o token).
 * Estados: desconectado / conectado (mostra a conta PÚBLICA, nunca o token). NÃO cobra
 * nada (G2). Gated por recurso `gateway` + permissão `gerenciar_pagamentos`.
 */
#[Layout('components.layouts.painel')]
#[Title('Gateway de pagamento')]
class Gateway extends Component
{
    public bool $conectado = false;

    /** Dados PÚBLICOS da conta (id/nome) — nunca o token. */
    public ?array $conta = null;

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_pagamentos'), 403);

        $this->carregar();

        // Mensagem vinda do callback (sucesso/erro da conexão).
        if ($msg = session('gateway_msg')) {
            Flux::toast($msg['texto'] ?? '', variant: ($msg['tipo'] ?? '') === 'erro' ? 'danger' : 'success');
        }
    }

    private function carregar(): void
    {
        $svc = app(ConexaoGatewayMercadoPago::class);
        $this->conectado = $svc->conectado();
        $this->conta = $svc->conta();
    }

    /** Inicia o OAuth: redireciona o dono para autorizar no Mercado Pago. */
    public function conectar()
    {
        abort_unless(auth('web')->user()?->can('gerenciar_pagamentos'), 403);

        try {
            $url = app(ConexaoGatewayMercadoPago::class)->iniciar((string) tenant('id'));
        } catch (PagamentoGatewayException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return null;
        }

        return redirect()->away($url);
    }

    public function desconectar(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_pagamentos'), 403);

        app(ConexaoGatewayMercadoPago::class)->desconectar();
        $this->carregar();

        Flux::toast('Conta do Mercado Pago desconectada.', variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.painel.pagamentos.gateway');
    }
}
