<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Livewire\Auth\Concerns\AutenticaPorGuard;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Login da equipe do estabelecimento (tenant, guard `web`).
 */
#[Layout('components.layouts.auth')]
#[Title('Acesso da equipe')]
class PainelLogin extends Component
{
    use AutenticaPorGuard;

    protected function credenciaisExtras(): array
    {
        return ['ativo' => true];
    }

    public function login()
    {
        $this->autenticar('web');

        return $this->redirectRoute('painel.dashboard', ['tenant' => tenant('id')], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.painel-login');
    }
}
