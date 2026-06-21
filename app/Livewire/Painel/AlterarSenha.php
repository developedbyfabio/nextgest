<?php

declare(strict_types=1);

namespace App\Livewire\Painel;

use App\Support\Senhas;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Alteração de senha self-service (menu de perfil do painel). Disponível a todos os
 * papéis (Dono/Gerente/Recepção/Profissional) — basta estar logado no painel.
 * Exige a senha ATUAL e aplica as mesmas regras de senha da troca obrigatória
 * (App\Support\Senhas). Embutido no layout do painel como um flux:modal.
 */
class AlterarSenha extends Component
{
    public string $atual = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function salvar(): void
    {
        $this->validate([
            'atual' => ['required', 'string'],
            'password' => Senhas::regrasNova(),
        ]);

        $user = auth('web')->user();

        if (! Hash::check($this->atual, $user->password)) {
            throw ValidationException::withMessages(['atual' => 'Senha atual incorreta.']);
        }

        $user->update(['password' => $this->password]);

        $this->reset(['atual', 'password', 'password_confirmation']);
        Flux::modal('alterar-senha')->close();
        Flux::toast('Senha alterada com sucesso.', variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.painel.alterar-senha');
    }
}
