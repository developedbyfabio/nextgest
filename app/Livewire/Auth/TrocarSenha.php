<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Support\Senhas;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Troca de senha OBRIGATÓRIA no 1º login do painel. O usuário já está autenticado
 * (entrou com a senha inicial); aqui define uma senha própria. Ao salvar, limpa a
 * flag `deve_trocar_senha` e volta ao painel. Layout de auth (sem navegação).
 */
#[Layout('components.layouts.auth')]
#[Title('Defina sua senha')]
class TrocarSenha extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public function mount()
    {
        $user = auth('web')->user();

        // Só faz sentido para quem precisa trocar; senão segue para o painel.
        if (! $user || ! $user->deve_trocar_senha) {
            return redirect()->route('painel.dashboard', ['tenant' => tenant('id')]);
        }
    }

    public function salvar()
    {
        $this->validate(['password' => Senhas::regrasNova()]);

        $user = auth('web')->user();

        $user->update([
            'password' => $this->password,
            'deve_trocar_senha' => false,
        ]);

        Flux::toast('Senha definida. Tudo certo!', variant: 'success');

        // Passo OPCIONAL de 2FA no 1º login: só para quem pode geri-lo (Dono) e ainda
        // não tem. É skippável (botão "Pular"). Os demais vão direto ao painel.
        if ($user->can('gerenciar_2fa_proprio') && ! $user->temDoisFatores()) {
            return redirect()->route('painel.2fa.onboarding', ['tenant' => tenant('id')]);
        }

        return redirect()->route('painel.dashboard', ['tenant' => tenant('id')]);
    }

    public function render(): View
    {
        return view('livewire.auth.trocar-senha');
    }
}
