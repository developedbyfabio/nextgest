<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\Cliente;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Registro do cliente final (tenant, guard `cliente`).
 * Telefone e e-mail obrigatórios; e-mail é o login.
 */
#[Layout('components.layouts.auth')]
#[Title('Criar conta')]
class ClienteRegistrar extends Component
{
    public string $nome = '';

    public string $telefone = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function registrar()
    {
        $dados = $this->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('clientes', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], attributes: [
            'nome' => 'nome',
            'telefone' => 'telefone',
            'email' => 'e-mail',
            'password' => 'senha',
        ]);

        $cliente = Cliente::create($dados);

        Auth::guard('cliente')->login($cliente);

        session()->regenerate();

        return $this->redirectRoute('tenant.home', ['tenant' => tenant('id')], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.cliente-registrar');
    }
}
