<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Papeis;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Edição de papéis e permissões (spatie). Permite editar as permissões de cada
 * papel e criar papéis personalizados. Permissão: editar_permissoes (Dono).
 *
 * Salvaguarda: o papel "Dono" sempre mantém todas as permissões (evita
 * lockout de quem administra o tenant).
 */
#[Layout('components.layouts.painel')]
#[Title('Papéis e permissões')]
class Index extends Component
{
    use AuthorizesRequests;

    public bool $mostrarFormulario = false;

    public ?int $editandoId = null;

    public string $nomePapel = '';

    /** @var array<string> nomes das permissões marcadas */
    public array $permissoesSelecionadas = [];

    public function mount(): void
    {
        $this->authorize('editar_permissoes');
    }

    protected function rules(): array
    {
        return [
            'nomePapel' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($this->editandoId)],
            'permissoesSelecionadas' => ['array'],
            'permissoesSelecionadas.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    public function novo(): void
    {
        $this->authorize('editar_permissoes');
        $this->reset(['editandoId', 'nomePapel', 'permissoesSelecionadas']);
        $this->resetValidation();
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $this->authorize('editar_permissoes');

        $papel = Role::with('permissions')->findOrFail($id);

        $this->editandoId = $papel->id;
        $this->nomePapel = $papel->name;
        $this->permissoesSelecionadas = $papel->permissions->pluck('name')->all();
        $this->resetValidation();
        $this->mostrarFormulario = true;
    }

    public function salvar(): void
    {
        $this->authorize('editar_permissoes');

        $dados = $this->validate();

        $papel = $this->editandoId
            ? tap(Role::findOrFail($this->editandoId))->update(['name' => $dados['nomePapel']])
            : Role::create(['name' => $dados['nomePapel'], 'guard_name' => 'web']);

        // O Dono é sempre todo-poderoso.
        $permissoes = $papel->name === 'Dono'
            ? Permission::pluck('name')->all()
            : ($dados['permissoesSelecionadas'] ?? []);

        $papel->syncPermissions($permissoes);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->mostrarFormulario = false;
        $this->reset(['editandoId', 'nomePapel', 'permissoesSelecionadas']);

        Flux::toast('Papel salvo.', variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.painel.papeis.index', [
            'papeis' => Role::withCount(['permissions', 'users'])->orderBy('name')->get(),
            'todasPermissoes' => Permission::orderBy('name')->pluck('name'),
            'donoSelecionado' => $this->nomePapel === 'Dono',
        ]);
    }
}
