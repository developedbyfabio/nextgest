<?php

declare(strict_types=1);

use App\Livewire\Painel\Servicos\Index;
use App\Models\Servico;
use App\Models\Unidade;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);
});

it('cria um serviço vinculado a unidades', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    $unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);

    Livewire::test(Index::class)
        ->call('novo')
        ->set('nome', 'Corte masculino')
        ->set('duracao_minutos', 30)
        ->set('preco', '45.00')
        ->set('unidades', [$unidade->id])
        ->call('salvar')
        ->assertHasNoErrors();

    $servico = Servico::firstWhere('nome', 'Corte masculino');
    expect($servico)->not->toBeNull();
    expect($servico->unidades->pluck('id')->all())->toBe([$unidade->id]);
});

it('valida campos obrigatórios do serviço', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->call('novo')
        ->set('nome', '')
        ->set('duracao_minutos', null)
        ->set('preco', null)
        ->call('salvar')
        ->assertHasErrors(['nome', 'duracao_minutos', 'preco']);
});

it('inativa serviço em vez de excluir', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    $servico = Servico::create(['nome' => 'Barba', 'duracao_minutos' => 20, 'preco' => 30, 'ativo' => true]);

    Livewire::test(Index::class)->call('inativar', $servico->id);

    expect(Servico::find($servico->id)->ativo)->toBeFalse();
});

it('bloqueia Profissional (403) na página de serviços', function () {
    $this->actingAs(usuarioComPapel('Profissional'), 'web')
        ->get('/lojaum/painel/servicos')
        ->assertForbidden();
});
