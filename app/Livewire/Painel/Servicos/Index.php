<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Servicos;

use App\Models\Servico;
use App\Models\Unidade;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * CRUD de serviços + vínculo com unidades (servico_unidade).
 * Permissões: criar_servico / editar_servico (Dono/Gerente).
 */
#[Layout('components.layouts.painel')]
#[Title('Serviços')]
class Index extends Component
{
    use AuthorizesRequests;

    public bool $mostrarFormulario = false;

    public ?int $editandoId = null;

    public string $nome = '';

    public string $descricao = '';

    public ?int $duracao_minutos = null;

    public ?string $preco = null;

    public ?string $percentual_comissao = null;

    public bool $ativo = true;

    /** @var array<int> ids de unidades onde o serviço é oferecido */
    public array $unidades = [];

    public function mount(): void
    {
        $this->authorize('editar_servico');
    }

    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'duracao_minutos' => ['required', 'integer', 'min:1'],
            'preco' => ['required', 'numeric', 'min:0'],
            'percentual_comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ativo' => ['boolean'],
            'unidades' => ['array'],
            'unidades.*' => ['integer', 'exists:unidades,id'],
        ];
    }

    public function novo(): void
    {
        $this->authorize('criar_servico');
        $this->resetForm();

        // Multi-unidade: com uma só filial, já vem selecionada (UI simplificada).
        $ativas = Unidade::where('ativo', true)->pluck('id');
        if ($ativas->count() === 1) {
            $this->unidades = [$ativas->first()];
        }

        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $this->authorize('editar_servico');

        $servico = Servico::with('unidades')->findOrFail($id);

        $this->editandoId = $servico->id;
        $this->nome = $servico->nome;
        $this->descricao = $servico->descricao ?? '';
        $this->duracao_minutos = $servico->duracao_minutos;
        $this->preco = (string) $servico->preco;
        $this->percentual_comissao = $servico->percentual_comissao !== null ? (string) $servico->percentual_comissao : null;
        $this->ativo = $servico->ativo;
        $this->unidades = $servico->unidades->pluck('id')->all();
        $this->resetValidation();
        $this->mostrarFormulario = true;
    }

    public function salvar(): void
    {
        $this->authorize($this->editandoId ? 'editar_servico' : 'criar_servico');

        $dados = $this->validate();
        $unidades = $dados['unidades'] ?? [];
        unset($dados['unidades']);

        // % de comissão vazia → null (sem comissão padrão para o serviço).
        $dados['percentual_comissao'] = ($dados['percentual_comissao'] ?? '') === '' ? null : $dados['percentual_comissao'];

        $servico = Servico::updateOrCreate(['id' => $this->editandoId], $dados);
        $servico->unidades()->sync($unidades);

        $this->mostrarFormulario = false;
        $this->resetForm();

        Flux::toast('Serviço salvo.', variant: 'success');
    }

    public function inativar(int $id): void
    {
        $this->authorize('editar_servico');
        Servico::whereKey($id)->update(['ativo' => false]);
        Flux::toast('Serviço inativado.');
    }

    public function reativar(int $id): void
    {
        $this->authorize('editar_servico');
        Servico::whereKey($id)->update(['ativo' => true]);
        Flux::toast('Serviço reativado.', variant: 'success');
    }

    protected function resetForm(): void
    {
        $this->reset(['editandoId', 'nome', 'descricao', 'duracao_minutos', 'preco', 'percentual_comissao', 'unidades']);
        $this->ativo = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.painel.servicos.index', [
            'servicos' => Servico::with('unidades')->orderBy('nome')->get(),
            'todasUnidades' => Unidade::where('ativo', true)->orderBy('nome')->get(),
        ]);
    }
}
