<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Unidades;

use App\Models\Servico;
use App\Models\Unidade;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * CRUD de unidades (filiais). "Excluir" = inativar (nunca apaga fisicamente).
 * Também gere, pela unidade, quais SERVIÇOS são oferecidos ali (servico_unidade)
 * e LISTA os profissionais atribuídos (atribuição/troca fica na tela Equipe, que
 * trata "uma unidade por profissional" + move de horários).
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

    public ?int $confirmarId = null;

    // Painel "Gerir" da unidade: serviços oferecidos ali.
    public bool $mostrarGerir = false;

    public ?int $gerindoId = null;

    /** @var array<int> ids dos serviços oferecidos na unidade sendo gerida */
    public array $servicosUnidade = [];

    public function mount(): void
    {
        $this->authorize('gerir_unidades');
    }

    /** Abre o painel de gestão da unidade (serviços + profissionais). */
    public function gerir(int $id): void
    {
        $this->authorize('gerir_unidades');

        $unidade = Unidade::with('servicos')->findOrFail($id);

        $this->gerindoId = $unidade->id;
        $this->servicosUnidade = $unidade->servicos->pluck('id')->all();
        $this->mostrarGerir = true;
    }

    /** Sincroniza os serviços oferecidos na unidade (servico_unidade, aditivo/multi). */
    public function salvarServicos(): void
    {
        $this->authorize('gerir_unidades');

        $this->validate([
            'servicosUnidade' => ['array'],
            'servicosUnidade.*' => ['integer', 'exists:servicos,id'],
        ]);

        Unidade::findOrFail($this->gerindoId)
            ->servicos()
            ->sync($this->servicosUnidade);

        Flux::toast('Serviços da unidade atualizados.', variant: 'success');
        $this->mostrarGerir = false;
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

    public function pedirInativar(int $id): void
    {
        $this->authorize('gerir_unidades');
        $this->confirmarId = $id;
        Flux::modal('inativar-unidade')->show();
    }

    public function inativar(int $id): void
    {
        $this->authorize('gerir_unidades');
        Unidade::whereKey($id)->update(['ativo' => false]);
        $this->confirmarId = null;
        Flux::modal('inativar-unidade')->close();
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
        // Profissionais da unidade sendo gerida + indicadores do que falta p/ agendar.
        $profissionaisUnidade = $this->gerindoId
            ? User::where('e_profissional', true)->where('ativo', true)
                ->whereHas('unidades', fn ($q) => $q->where('unidades.id', $this->gerindoId))
                ->withCount([
                    'servicos',
                    'horariosTrabalho as horarios_count' => fn ($q) => $q->where('unidade_id', $this->gerindoId),
                ])
                ->orderBy('name')->get()
            : collect();

        return view('livewire.painel.unidades.index', [
            'unidades' => Unidade::orderBy('nome')->get(),
            'todosServicos' => Servico::where('ativo', true)->orderBy('nome')->get(),
            'gerindo' => $this->gerindoId ? Unidade::find($this->gerindoId) : null,
            'profissionaisUnidade' => $profissionaisUnidade,
        ]);
    }
}
