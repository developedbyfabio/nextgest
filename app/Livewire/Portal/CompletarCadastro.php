<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Rules\CelularBr;
use App\Rules\Cpf;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Complete seu cadastro" (D94 CPF → D96 telefone) — destino do gate
 * ExigirPerfilCompletoCliente. Coleta num ÚNICO passo o que faltar: telefone (válido,
 * p/ WhatsApp) e/ou CPF (válido + único por tenant). Nome vem pré-preenchido (Google)
 * e editável. Reusa App\Rules\CelularBr e App\Rules\Cpf — não cria validação nova.
 */
#[Layout('components.layouts.portal-auth')]
#[Title('Complete seu cadastro')]
class CompletarCadastro extends Component
{
    public string $nome = '';

    public string $telefone = '';

    public string $cpf = '';

    // Quais campos faltam (definido no mount pelo estado do cliente) — controla o que
    // a tela mostra e o que é exigido. A validação recomputa do banco no salvar.
    public bool $precisaTelefone = false;

    public bool $precisaCpf = false;

    public function mount()
    {
        $cliente = Auth::guard('cliente')->user();
        abort_unless($cliente !== null, 403);

        // Perfil já completo → nada a fazer; volta ao portal.
        if (! $cliente->perfilIncompleto()) {
            return $this->redirectRoute('tenant.home', ['tenant' => tenant('id')], navigate: true);
        }

        $this->nome = (string) $cliente->nome;
        $this->telefone = (string) $cliente->telefone; // pré-preenche se houver
        $this->precisaTelefone = blank($cliente->telefone);
        $this->precisaCpf = blank($cliente->cpf);
    }

    public function salvar()
    {
        $cliente = Auth::guard('cliente')->user();

        // Recomputa do estado REAL do cliente (não confia em prop hidratada do cliente).
        $precisaTelefone = blank($cliente->telefone);
        $precisaCpf = blank($cliente->cpf);

        // Normaliza para dígitos antes de validar/gravar (telefone e CPF viram dígitos;
        // a máscara é só do input; o WhatsApp/EvolutionGateway normaliza no envio).
        $this->telefone = preg_replace('/\D+/', '', $this->telefone);
        $this->cpf = preg_replace('/\D+/', '', $this->cpf);

        $regras = ['nome' => ['required', 'string', 'max:255']];

        if ($precisaTelefone) {
            $regras['telefone'] = ['required', 'string', new CelularBr];
        }

        if ($precisaCpf) {
            $regras['cpf'] = ['required', 'string', new Cpf, Rule::unique('clientes', 'cpf')->ignore($cliente->id)];
        }

        $this->validate($regras, messages: [
            'cpf.unique' => 'CPF já cadastrado.',
        ], attributes: [
            'nome' => 'nome',
            'telefone' => 'telefone',
            'cpf' => 'CPF',
        ]);

        $cliente->nome = $this->nome;

        if ($precisaTelefone) {
            $cliente->telefone = $this->telefone;
        }

        if ($precisaCpf) {
            $cliente->cpf = $this->cpf;
        }

        $cliente->save();

        return $this->redirectRoute('tenant.home', ['tenant' => tenant('id')], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.portal.completar-cadastro');
    }
}
