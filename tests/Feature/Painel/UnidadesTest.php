<?php

declare(strict_types=1);

use App\Livewire\Painel\Unidades\Index;
use App\Models\Unidade;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);
});

it('exibe a página de unidades para o Dono', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web')
        ->get('/lojaum/painel/unidades')
        ->assertOk()
        ->assertSee('Unidades');
});

it('cria uma unidade', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->call('novo')
        ->set('nome', 'Matriz Centro')
        ->set('telefone', '1133334444')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(Unidade::where('nome', 'Matriz Centro')->exists())->toBeTrue();
});

it('exige nome ao salvar unidade', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->call('novo')
        ->set('nome', '')
        ->call('salvar')
        ->assertHasErrors('nome');
});

it('inativa em vez de excluir', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    $unidade = Unidade::create(['nome' => 'Filial', 'ativo' => true]);

    Livewire::test(Index::class)->call('inativar', $unidade->id);

    expect(Unidade::find($unidade->id)->ativo)->toBeFalse();
    expect(Unidade::find($unidade->id))->not->toBeNull();
});

it('bloqueia Profissional (403) na página de unidades', function () {
    $this->actingAs(usuarioComPapel('Profissional'), 'web')
        ->get('/lojaum/painel/unidades')
        ->assertForbidden();
});

it('bloqueia Recepção (403) na página de unidades', function () {
    $this->actingAs(usuarioComPapel('Recepção'), 'web')
        ->get('/lojaum/painel/unidades')
        ->assertForbidden();
});
