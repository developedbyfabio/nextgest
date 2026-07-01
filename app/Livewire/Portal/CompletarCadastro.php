<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Rules\Cpf;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Complete seu cadastro (CPF)" (D94) — destino do gate ExigirCpfCliente. O cliente
 * logado sem CPF informa aqui (obrigatório, válido e único por tenant) e volta ao
 * portal. Ponto único, reutilizado pelo fluxo do Google (fatia seguinte).
 */
#[Layout('components.layouts.portal-auth')]
#[Title('Complete seu cadastro')]
class CompletarCadastro extends Component
{
    public string $cpf = '';

    public function mount()
    {
        $cliente = Auth::guard('cliente')->user();
        abort_unless($cliente !== null, 403);

        // Já tem CPF → nada a completar; volta ao portal.
        if (filled($cliente->cpf)) {
            return $this->redirectRoute('tenant.home', ['tenant' => tenant('id')], navigate: true);
        }
    }

    public function salvar()
    {
        $cliente = Auth::guard('cliente')->user();

        // Só dígitos antes do unique (mesma normalização do autocadastro).
        $this->cpf = preg_replace('/\D+/', '', $this->cpf);

        $this->validate([
            'cpf' => ['required', 'string', new Cpf, Rule::unique('clientes', 'cpf')->ignore($cliente->id)],
        ], messages: [
            'cpf.unique' => 'CPF já cadastrado.',
        ], attributes: [
            'cpf' => 'CPF',
        ]);

        $cliente->cpf = $this->cpf;
        $cliente->save();

        return $this->redirectRoute('tenant.home', ['tenant' => tenant('id')], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.portal.completar-cadastro');
    }
}
