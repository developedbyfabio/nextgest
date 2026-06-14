<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Unidades;

use App\Models\Unidade;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * CRUD de unidades (filiais). "Excluir" = inativar (nunca apaga fisicamente).
 * Permissão: gerir_unidades (Dono/Gerente).
 */
#[Layout('components.layouts.painel')]
#[Title('Unidades')]
class Index extends Component
{
    use AuthorizesRequests;

    public bool $mostrarFormulario = false;

    public ?int $editandoId = null;

    public string $nome = '';

    public string $endereco = '';

    public string $telefone = '';

    public bool $ativo = true;

    public function mount(): void
    {
        $this->authorize('gerir_unidades');
    }

    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'ativo' => ['boolean'],
        ];
    }

    public function novo(): void
    {
        $this->authorize('gerir_unidades');
        $this->resetForm();
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $this->authorize('gerir_unidades');

        $unidade = Unidade::findOrFail($id);

        $this->editandoId = $unidade->id;
        $this->nome = $unidade->nome;
        $this->endereco = $unidade->endereco ?? '';
        $this->telefone = $unidade->telefone ?? '';
        $this->ativo = $unidade->ativo;
        $this->resetValidation();
        $this->mostrarFormulario = true;
    }

    public function salvar(): void
    {
        $this->authorize('gerir_unidades');

        $dados = $this->validate();

        Unidade::updateOrCreate(['id' => $this->editandoId], $dados);

        $this->mostrarFormulario = false;
        $this->resetForm();

        Flux::toast('Unidade salva.', variant: 'success');
    }

    public function inativar(int $id): void
    {
        $this->authorize('gerir_unidades');
        Unidade::whereKey($id)->update(['ativo' => false]);
        Flux::toast('Unidade inativada.');
    }

    public function reativar(int $id): void
    {
        $this->authorize('gerir_unidades');
        Unidade::whereKey($id)->update(['ativo' => true]);
        Flux::toast('Unidade reativada.', variant: 'success');
    }

    protected function resetForm(): void
    {
        $this->reset(['editandoId', 'nome', 'endereco', 'telefone']);
        $this->ativo = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.painel.unidades.index', [
            'unidades' => Unidade::orderBy('nome')->get(),
        ]);
    }
}
