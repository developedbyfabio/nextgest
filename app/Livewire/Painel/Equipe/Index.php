<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Equipe;

use App\Models\Servico;
use App\Models\Unidade;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * CRUD da equipe (users) + papel (spatie), vínculo de unidades (user_unidade) e,
 * para profissionais, serviços que sabe executar (servico_user).
 * "Excluir" = inativar. Permissões: criar_usuario / editar_usuario.
 */
#[Layout('components.layouts.painel')]
#[Title('Equipe')]
class Index extends Component
{
    use AuthorizesRequests;

    public bool $mostrarFormulario = false;

    public ?int $editandoId = null;

    public string $name = '';

    public string $email = '';

    /** @var array<string> Papéis (spatie) do membro. Um membro pode ter VÁRIOS (ex.: Dono + Profissional). */
    public array $papeis = [];

    public bool $e_profissional = false;

    public bool $ativo = true;

    public string $password = '';

    /** @var array<int> */
    public array $unidades = [];

    /** @var array<int> */
    public array $servicos = [];

    public ?int $confirmarId = null;

    public string $busca = '';

    public function mount(): void
    {
        $this->authorize('editar_usuario');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editandoId)],
            'papeis' => ['required', 'array', 'min:1'],
            'papeis.*' => ['string', Rule::exists('roles', 'name')],
            'e_profissional' => ['boolean'],
            'ativo' => ['boolean'],
            'password' => [$this->editandoId ? 'nullable' : 'required', 'string', 'min:8'],
            'unidades' => ['array'],
            'unidades.*' => ['integer', 'exists:unidades,id'],
            'servicos' => ['array'],
            'servicos.*' => ['integer', 'exists:servicos,id'],
        ];
    }

    public function novo(): void
    {
        $this->authorize('criar_usuario');
        $this->resetForm();

        $ativas = Unidade::where('ativo', true)->pluck('id');
        if ($ativas->count() === 1) {
            $this->unidades = [$ativas->first()];
        }

        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $this->authorize('editar_usuario');

        $user = User::with(['unidades', 'servicos', 'roles'])->findOrFail($id);

        $this->editandoId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->papeis = $user->roles->pluck('name')->all();
        $this->e_profissional = $user->e_profissional;
        $this->ativo = $user->ativo;
        $this->password = '';
        $this->unidades = $user->unidades->pluck('id')->all();
        $this->servicos = $user->servicos->pluck('id')->all();
        $this->resetValidation();
        $this->mostrarFormulario = true;
    }

    public function salvar(): void
    {
        $this->authorize($this->editandoId ? 'editar_usuario' : 'criar_usuario');

        $dados = $this->validate();

        $user = $this->editandoId ? User::findOrFail($this->editandoId) : new User;

        // Trava multi-tenant: o estabelecimento não pode ficar SEM Dono ativo. Editar
        // não pode retirar o papel Dono (nem inativar) do único Dono ativo.
        if ($this->editandoId) {
            $eraDonoAtivo = $user->ativo && $user->hasRole('Dono');
            $continuaDonoAtivo = in_array('Dono', $dados['papeis'], true) && $dados['ativo'];
            if ($eraDonoAtivo && ! $continuaDonoAtivo && $this->donosAtivosExceto($user->id) === 0) {
                $this->addError('papeis', 'Este é o último Dono ativo do estabelecimento. Defina outro Dono antes de retirar o papel ou inativar.');

                return;
            }
        }

        $user->name = $dados['name'];
        $user->email = $dados['email'];
        $user->e_profissional = $dados['e_profissional'];
        $user->ativo = $dados['ativo'];

        if (filled($dados['password'])) {
            $user->password = $dados['password']; // cast 'hashed'
        }

        $user->save();
        $user->syncRoles($dados['papeis']);
        $user->unidades()->sync($dados['unidades'] ?? []);
        $user->servicos()->sync($dados['e_profissional'] ? ($dados['servicos'] ?? []) : []);

        $this->mostrarFormulario = false;
        $this->resetForm();

        Flux::toast('Membro salvo.', variant: 'success');
    }

    public function pedirInativar(int $id): void
    {
        $this->authorize('editar_usuario');
        $this->confirmarId = $id;
        Flux::modal('inativar-membro')->show();
    }

    public function inativar(int $id): void
    {
        $this->authorize('editar_usuario');
        $this->confirmarId = null;
        Flux::modal('inativar-membro')->close();

        if ($id === auth('web')->id()) {
            Flux::toast('Você não pode inativar a própria conta.', variant: 'danger');

            return;
        }

        $alvo = User::find($id);
        if ($alvo && $alvo->ativo && $alvo->hasRole('Dono') && $this->donosAtivosExceto($id) === 0) {
            Flux::toast('Não é possível inativar o último Dono ativo do estabelecimento.', variant: 'danger');

            return;
        }

        User::whereKey($id)->update(['ativo' => false]);
        Flux::toast('Membro inativado.');
    }

    /** Quantos OUTROS Donos ativos existem (exclui o usuário informado). */
    private function donosAtivosExceto(?int $id): int
    {
        return User::role('Dono')
            ->where('ativo', true)
            ->when($id, fn ($q) => $q->whereKeyNot($id))
            ->count();
    }

    public function reativar(int $id): void
    {
        $this->authorize('editar_usuario');
        User::whereKey($id)->update(['ativo' => true]);
        Flux::toast('Membro reativado.', variant: 'success');
    }

    protected function resetForm(): void
    {
        $this->reset(['editandoId', 'name', 'email', 'papeis', 'e_profissional', 'password', 'unidades', 'servicos']);
        $this->ativo = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.painel.equipe.index', [
            'membros' => User::with('roles')
                ->when($this->busca !== '', fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$this->busca.'%')->orWhere('email', 'like', '%'.$this->busca.'%')))
                ->orderBy('name')->get(),
            'todosPapeis' => Role::orderBy('name')->pluck('name'),
            'todasUnidades' => Unidade::where('ativo', true)->orderBy('nome')->get(),
            'todosServicos' => Servico::where('ativo', true)->orderBy('nome')->get(),
        ]);
    }
}
