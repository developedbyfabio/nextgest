<?php

declare(strict_types=1);

use App\Livewire\Painel\Papeis\Index;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);
});

it('cria um papel personalizado com permissões', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->call('novo')
        ->set('nomePapel', 'Caixa')
        ->set('permissoesSelecionadas', ['criar_venda', 'ver_clientes'])
        ->call('salvar')
        ->assertHasNoErrors();

    $papel = Role::where('name', 'Caixa')->first();
    expect($papel)->not->toBeNull();
    expect($papel->permissions->pluck('name')->sort()->values()->all())->toBe(['criar_venda', 'ver_clientes']);
});

it('edita as permissões de um papel existente', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    $recepcao = Role::where('name', 'Recepção')->first();

    Livewire::test(Index::class)
        ->call('editar', $recepcao->id)
        ->set('permissoesSelecionadas', ['ver_agenda'])
        ->call('salvar')
        ->assertHasNoErrors();

    expect($recepcao->fresh()->permissions->pluck('name')->all())->toBe(['ver_agenda']);
});

it('mantém o Dono com todas as permissões mesmo se desmarcadas', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    $dono = Role::where('name', 'Dono')->first();
    $totalPerms = Permission::count();

    Livewire::test(Index::class)
        ->call('editar', $dono->id)
        ->set('permissoesSelecionadas', [])
        ->call('salvar')
        ->assertHasNoErrors();

    expect($dono->fresh()->permissions->count())->toBe($totalPerms);
});

it('bloqueia quem não tem editar_permissoes (Gerente, 403)', function () {
    $this->actingAs(usuarioComPapel('Gerente'), 'web')
        ->get('/lojaum/painel/papeis')
        ->assertForbidden();
});
