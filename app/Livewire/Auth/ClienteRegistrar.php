<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\Cliente;
use App\Rules\CelularBr;
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
        // Normaliza telefone e CPF para dígitos ANTES de validar (a máscara é do input,
        // client-side): o CPF precisa disso p/ o unique bater com o armazenado e o
        // telefone é gravado em dígitos limpos — MESMO formato da tela de completar
        // cadastro (D96) e do admin; o envio do WhatsApp (EvolutionGateway) prefixa 55.
        $this->cpf = preg_replace('/\D+/', '', $this->cpf);
        $this->telefone = preg_replace('/\D+/', '', $this->telefone);

        $dados = $this->validate([
            'nome' => ['required', 'string', 'max:255'],
            // Telefone válido (mesma regra CelularBr do completar cadastro/admin), não
            // mais o `max:30` frouxo — evita cadastrar número que quebra o WhatsApp.
            'telefone' => ['required', 'string', new CelularBr],
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
