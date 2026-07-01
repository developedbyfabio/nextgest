<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\Cliente;
use App\Rules\Cpf;
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
#[Layout('components.layouts.portal-auth')]
#[Title('Criar conta')]
class ClienteRegistrar extends Component
{
    public string $nome = '';

    public string $telefone = '';

    public string $email = '';

    public string $cpf = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function registrar()
    {
        // Normaliza o CPF para dígitos ANTES de validar: a Rule\Cpf aceita mascarado,
        // mas o unique precisa comparar com o valor armazenado (só dígitos). A máscara
        // é do input (client-side); no banco vão 11 dígitos.
        $this->cpf = preg_replace('/\D+/', '', $this->cpf);

        $dados = $this->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('clientes', 'email')],
            // CPF obrigatório no autocadastro, válido e ÚNICO POR TENANT (a conexão
            // default é o banco do tenant durante a tenancy) — anti conta duplicada.
            'cpf' => ['required', 'string', new Cpf, Rule::unique('clientes', 'cpf')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], messages: [
            'cpf.unique' => 'CPF já cadastrado.',
        ], attributes: [
            'nome' => 'nome',
            'telefone' => 'telefone',
            'email' => 'e-mail',
            'cpf' => 'CPF',
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
