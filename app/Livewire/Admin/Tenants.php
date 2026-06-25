<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Estabelecimento;
use App\Models\Tenant;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Gestão mínima de estabelecimentos (tenants) no painel central (guard admin).
 * Versão reduzida da futura Fatia 8. Criar tenant dispara o fluxo normal
 * (banco + migrations + seed) via App\Models\Tenant::create().
 */
#[Layout('components.layouts.admin')]
#[Title('Estabelecimentos')]
class Tenants extends Component
{
    use WithPagination;

    public string $busca = '';

    // Criar tenant
    public bool $mostrarFormulario = false;

    public string $nome = '';

    public string $slug = '';

    // Criar dono
    public bool $mostrarDono = false;

    public ?string $tenantDono = null;

    public string $donoNome = '';

    public string $donoEmail = '';

    public string $donoSenha = '';

    public function mount(): void
    {
        abort_unless(auth('admin')->check(), 403);
    }

    public function updatedBusca(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::notIn(config('nextgest.reserved_slugs', [])),
                Rule::unique('tenants', 'id'),
                Rule::unique('tenants', 'slug'),
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'slug.regex' => 'O slug deve ter apenas letras minúsculas, números e hífen, começando por letra ou número.',
            'slug.not_in' => 'Este slug é reservado e não pode ser usado.',
            'slug.unique' => 'Já existe um estabelecimento com este slug.',
        ];
    }

    public function novo(): void
    {
        $this->reset(['nome', 'slug']);
        $this->resetValidation();
        $this->mostrarFormulario = true;
    }

    public function criar(): void
    {
        $dados = $this->validate();

        // Dispara CreateDatabase + MigrateDatabase + SeedDatabase (pipeline do tenant).
        Tenant::create([
            'id' => $dados['slug'],
            'nome' => $dados['nome'],
            'slug' => $dados['slug'],
            'ativo' => true,
        ]);

        // Cadastro CENTRAL mínimo (1:1; D56) — o resto fica nulo e é completado na tela "Dados".
        Estabelecimento::create([
            'tenant_id' => $dados['slug'],
            'nome_fantasia' => $dados['nome'],
        ]);

        $this->mostrarFormulario = false;
        $this->reset(['nome', 'slug']);

        Flux::toast('Estabelecimento criado.', variant: 'success');
    }

    public function inativar(string $id): void
    {
        Tenant::whereKey($id)->update(['ativo' => false]);
        Flux::toast('Estabelecimento inativado.');
    }

    public function ativar(string $id): void
    {
        Tenant::whereKey($id)->update(['ativo' => true]);
        Flux::toast('Estabelecimento ativado.', variant: 'success');
    }

    public function abrirDono(string $id): void
    {
        $this->tenantDono = $id;
        $this->reset(['donoNome', 'donoEmail', 'donoSenha']);
        $this->resetValidation();
        $this->mostrarDono = true;
    }

    public function criarDono(): void
    {
        $dados = $this->validate([
            'donoNome' => ['required', 'string', 'max:255'],
            'donoEmail' => ['required', 'string', 'email', 'max:255'],
            'donoSenha' => ['required', 'string', 'min:8'],
        ], attributes: [
            'donoNome' => 'nome',
            'donoEmail' => 'e-mail',
            'donoSenha' => 'senha',
        ]);

        $tenant = Tenant::findOrFail($this->tenantDono);

        $erro = $tenant->run(function () use ($dados) {
            if (User::where('email', $dados['donoEmail'])->exists()) {
                return 'Já existe um usuário com este e-mail neste estabelecimento.';
            }

            $user = User::create([
                'name' => $dados['donoNome'],
                'email' => $dados['donoEmail'],
                'password' => $dados['donoSenha'], // cast 'hashed'
                'e_profissional' => false,
                'ativo' => true,
            ]);

            $user->assignRole('Dono');

            return null;
        });

        if ($erro !== null) {
            $this->addError('donoEmail', $erro);

            return;
        }

        // Backfill leve do contato do dono no cadastro central, só nos campos ainda vazios
        // (não sobrescreve dados já preenchidos). O contato completo vem pela tela "Dados".
        $est = Estabelecimento::where('tenant_id', $this->tenantDono)->first();
        if ($est) {
            $est->dono_nome ??= $dados['donoNome'];
            $est->dono_email ??= $dados['donoEmail'];
            $est->save();
        }

        $this->mostrarDono = false;
        $this->reset(['donoNome', 'donoEmail', 'donoSenha']);

        Flux::toast('Dono criado.', variant: 'success');
    }

    public function render(): View
    {
        $tenants = Tenant::query()
            ->when($this->busca !== '', function ($q) {
                $termo = '%'.$this->busca.'%';
                $q->where('nome', 'like', $termo)->orWhere('slug', 'like', $termo);
            })
            ->orderBy('nome')
            ->paginate(10);

        return view('livewire.admin.tenants', ['tenants' => $tenants]);
    }
}
