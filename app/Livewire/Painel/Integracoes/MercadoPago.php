<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Integracoes;

use App\Models\GatewayPagamento;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Editor da integração Mercado Pago (cofre reusado: tabela `gateways_pagamento`,
 * model App\Models\GatewayPagamento, `credenciais` cast `encrypted:array`).
 *
 * Rota gated por `recurso:gateway` (flag 0a) + `can:gerenciar_pagamentos`.
 *
 * Segredo WRITE-ONLY: o Access Token NUNCA é renderizado de volta — o campo carrega
 * vazio; salvar com vazio MANTÉM o anterior, preenchido SUBSTITUI. A tela mostra só
 * status + máscara (••••1234). Esta fase NÃO chama a API do Mercado Pago.
 */
#[Layout('components.layouts.painel')]
#[Title('Integração · Mercado Pago')]
class MercadoPago extends Component
{
    public bool $ativo = false;

    public string $modo = 'sandbox';

    /** Write-only: sempre vazio ao carregar; nunca recebe o segredo. */
    public string $access_token = '';

    public bool $configurado = false;

    public string $mascara = '';

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_pagamentos'), 403);

        if ($g = $this->registro()) {
            $this->ativo = (bool) $g->ativo;
            $this->modo = $g->modo ?: 'sandbox';
            $token = $g->credenciais['access_token'] ?? null;
            $this->configurado = filled($token);
            $this->mascara = $this->mascarar($token);
        }
    }

    public function salvar(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_pagamentos'), 403);

        $this->validate([
            'modo' => ['required', Rule::in(['sandbox', 'producao'])],
            'access_token' => ['nullable', 'string', 'max:500'],
        ], attributes: ['access_token' => 'Access Token']);

        $g = $this->registro() ?? new GatewayPagamento(['provedor' => 'mercadopago']);
        $cred = $g->credenciais ?? [];

        // Write-only: só toca no segredo se veio preenchido.
        if (filled($this->access_token)) {
            $cred['access_token'] = trim($this->access_token);
        }

        $g->provedor = 'mercadopago';
        $g->apelido = $g->apelido ?: 'Mercado Pago';
        $g->credenciais = $cred;
        $g->modo = $this->modo;
        $g->ativo = $this->ativo;
        $g->padrao = true;
        $g->save();

        $this->access_token = ''; // nunca mantém o segredo no estado do componente
        $this->configurado = filled($cred['access_token'] ?? null);
        $this->mascara = $this->mascarar($cred['access_token'] ?? null);

        Flux::toast('Integração salva.', variant: 'success');
    }

    private function registro(): ?GatewayPagamento
    {
        return GatewayPagamento::where('provedor', 'mercadopago')->first();
    }

    private function mascarar(?string $valor): string
    {
        return blank($valor) ? '' : '••••'.substr($valor, -4);
    }

    public function render(): View
    {
        return view('livewire.painel.integracoes.mercado-pago');
    }
}
