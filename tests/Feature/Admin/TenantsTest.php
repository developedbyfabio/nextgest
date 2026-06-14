<?php

declare(strict_types=1);

use App\Livewire\Admin\Tenants;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function admin(): Admin
{
    return Admin::create([
        'name' => 'Super',
        'email' => 'super@nextgest.com.br',
        'password' => 'senha-super-12345',
        'ativo' => true,
    ]);
}

afterEach(function () {
    // Limpa arquivos sqlite de tenants criados pelos testes.
    foreach (glob(database_path('tenant_*')) as $arquivo) {
        @unlink($arquivo);
    }
});

it('exige super-admin para acessar a gestão de tenants', function () {
    $this->get('/admin/estabelecimentos')->assertRedirect(route('admin.login'));
});

it('renderiza a página completa (layout admin) para o super-admin', function () {
    $this->actingAs(admin(), 'admin')
        ->get('/admin/estabelecimentos')
        ->assertOk()
        ->assertSee('Estabelecimentos');
});

it('lista os estabelecimentos para o super-admin', function () {
    criarTenant('lojaum');
    $this->actingAs(admin(), 'admin');

    Livewire::test(Tenants::class)->assertSee('Lojaum');
});

it('cria um estabelecimento e provisiona o banco', function () {
    $this->actingAs(admin(), 'admin');

    Livewire::test(Tenants::class)
        ->call('novo')
        ->set('nome', 'Barbearia do Jorge')
        ->set('slug', 'barbeariadojorge')
        ->call('criar')
        ->assertHasNoErrors();

    $tenant = Tenant::find('barbeariadojorge');
    expect($tenant)->not->toBeNull()
        ->and($tenant->nome)->toBe('Barbearia do Jorge')
        ->and($tenant->ativo)->toBeTrue();

    // O banco do tenant foi criado e migrado (papéis semeados).
    $papeis = $tenant->run(fn () => Role::count());
    expect($papeis)->toBeGreaterThan(0);
});

it('valida slug reservado, formato e duplicidade', function () {
    criarTenant('existente');
    $this->actingAs(admin(), 'admin');

    // Reservado
    Livewire::test(Tenants::class)
        ->set('nome', 'X')->set('slug', 'admin')->call('criar')
        ->assertHasErrors('slug');

    // Formato inválido
    Livewire::test(Tenants::class)
        ->set('nome', 'X')->set('slug', 'Com Espaço')->call('criar')
        ->assertHasErrors('slug');

    // Duplicado
    Livewire::test(Tenants::class)
        ->set('nome', 'X')->set('slug', 'existente')->call('criar')
        ->assertHasErrors('slug');
});

it('inativa e reativa sem apagar o banco', function () {
    criarTenant('lojaum');
    $this->actingAs(admin(), 'admin');

    Livewire::test(Tenants::class)->call('inativar', 'lojaum');
    expect(Tenant::find('lojaum')->ativo)->toBeFalse();

    Livewire::test(Tenants::class)->call('ativar', 'lojaum');
    expect(Tenant::find('lojaum')->ativo)->toBeTrue();

    // O banco do tenant continua existindo.
    expect(Tenant::find('lojaum')->run(fn () => true))->toBeTrue();
});

it('cria o dono inicial de um tenant', function () {
    criarTenant('lojaum');
    $this->actingAs(admin(), 'admin');

    Livewire::test(Tenants::class)
        ->call('abrirDono', 'lojaum')
        ->set('donoNome', 'Jorge')
        ->set('donoEmail', 'jorge@lojaum.com')
        ->set('donoSenha', 'senha-inicial-123')
        ->call('criarDono')
        ->assertHasNoErrors();

    $tem = Tenant::find('lojaum')->run(function () {
        $u = User::where('email', 'jorge@lojaum.com')->first();

        return $u && $u->hasRole('Dono');
    });

    expect($tem)->toBeTrue();
});
