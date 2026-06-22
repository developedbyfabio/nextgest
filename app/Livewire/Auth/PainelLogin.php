<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Livewire\Auth\Concerns\AutenticaPorGuard;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
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

    /** Só o Dono com 2FA confirmado exige o segundo fator; os demais logam só com senha. */
    protected function precisaSegundoFator(Authenticatable $user): bool
    {
        return $user instanceof User && $user->temDoisFatores();
    }

    public function login()
    {
        $pendente = $this->autenticar('web');

        // 2FA ativo: senha OK, mas falta o segundo fator. Guarda a pendência (só id +
        // remember, sem segredo) e manda ao desafio. Até passar, não há acesso a nada.
        if ($pendente !== null) {
            session()->put('2fa.pendente', [
                'id' => $pendente->getAuthIdentifier(),
                'remember' => $this->remember,
            ]);

            return $this->redirectRoute('painel.2fa.desafio', ['tenant' => tenant('id')], navigate: true);
        }

        return $this->redirectRoute('painel.dashboard', ['tenant' => tenant('id')], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.painel-login');
    }
}
