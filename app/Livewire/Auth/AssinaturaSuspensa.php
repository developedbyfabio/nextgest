<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\Assinatura;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tela amigável de "assinatura pausada por falta de pagamento" (D60). Destino do
 * GarantirAssinaturaAtiva quando a assinatura está `suspensa`/`cancelada`.
 *
 * Acessível sem login (o dono cai aqui ao tentar acessar o painel). ISENTA do
 * middleware (não cria loop). Mostra a fatura pendente; quando houver `link_pagamento`
 * (gateway, Fase 5), exibe "Pagar agora" — por ora o link é nulo (orientação manual).
 */
#[Layout('components.layouts.auth')]
#[Title('Assinatura pausada')]
class AssinaturaSuspensa extends Component
{
    public function mount()
    {
        // Só faz sentido quando realmente bloqueada; caso contrário, volta ao login.
        $situacao = tenant()?->assinatura?->situacaoAcesso();

        if (! in_array($situacao, [Assinatura::SUSPENSA, Assinatura::CANCELADA], true)) {
            return redirect()->route('painel.login', ['tenant' => tenant('id')]);
        }
    }

    public function render(): View
    {
        $assinatura = tenant()?->assinatura;

        return view('livewire.auth.assinatura-suspensa', [
            'fatura' => $assinatura?->faturaPendente(),
            'cancelada' => $assinatura?->situacaoAcesso() === Assinatura::CANCELADA,
        ]);
    }
}
