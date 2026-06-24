<?php

declare(strict_types=1);

use App\Livewire\Painel\Unidades\Index;
use App\Models\Servico;
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

it('gerir serviços pela unidade sincroniza servico_unidade (passa a aparecer pro cliente)', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    $unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);
    $barba = Servico::create(['nome' => 'Barba', 'duracao_minutos' => 20, 'preco' => 30, 'ativo' => true]);

    Livewire::test(Index::class)
        ->call('gerir', $unidade->id)
        ->assertSet('servicosUnidade', [])
        ->set('servicosUnidade', [$corte->id, $barba->id])
        ->call('salvarServicos')
        ->assertHasNoErrors();

    expect($unidade->fresh()->servicos->pluck('id')->sort()->values()->all())
        ->toBe(collect([$corte->id, $barba->id])->sort()->values()->all());

    // Desmarcar um remove o vínculo (aditivo/multi, espelha o pivô).
    Livewire::test(Index::class)
        ->call('gerir', $unidade->id)
        ->set('servicosUnidade', [$corte->id])
        ->call('salvarServicos');

    expect($unidade->fresh()->servicos->pluck('id')->all())->toBe([$corte->id]);
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
