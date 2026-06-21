<?php

declare(strict_types=1);

use App\Models\Bloqueio;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

/**
 * Elevação de UI dos cadastros (Polimento 2): confirmação por flux:modal (sem
 * confirm nativo), estados temáticos e busca. NÃO altera regras/permissões — só a
 * apresentação. Os testes de regra de cada cadastro seguem à parte.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojacad');
    tenancy()->initialize($this->tenant);
    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->dono = usuarioComPapel('Dono', ['email' => 'dono@cad.test']);
    Livewire::actingAs($this->dono, 'web');
});

it('unidades: inativar passa por modal de confirmação (sem confirm nativo)', function () {
    $u = Unidade::create(['nome' => 'Filial', 'ativo' => true]);

    Livewire::test(App\Livewire\Painel\Unidades\Index::class)
        ->assertDontSeeHtml('wire:confirm')
        ->call('pedirInativar', $u->id)
        ->assertSet('confirmarId', $u->id)
        ->call('inativar', $u->id)
        ->assertSet('confirmarId', null);

    expect($u->fresh()->ativo)->toBeFalse()
        ->and(Unidade::find($u->id))->not->toBeNull(); // não apagou
});

it('serviços: busca filtra a lista e inativar usa modal', function () {
    Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45, 'ativo' => true]);
    Servico::create(['nome' => 'Barba', 'duracao_minutos' => 20, 'preco' => 30, 'ativo' => true]);

    Livewire::test(App\Livewire\Painel\Servicos\Index::class)
        ->assertSee('Corte')->assertSee('Barba')
        ->set('busca', 'Cor')
        ->assertSee('Corte')->assertDontSee('Barba');

    $s = Servico::where('nome', 'Corte')->first();
    Livewire::test(App\Livewire\Painel\Servicos\Index::class)
        ->call('pedirInativar', $s->id)->assertSet('confirmarId', $s->id)
        ->call('inativar', $s->id);
    expect($s->fresh()->ativo)->toBeFalse();
});

it('equipe: busca por nome/e-mail e inativar por modal', function () {
    usuarioComPapel('Recepção', ['name' => 'Ana Recep', 'email' => 'ana@cad.test']);
    $bruno = usuarioComPapel('Profissional', ['name' => 'Bruno Prof', 'email' => 'bruno@cad.test', 'e_profissional' => true]);

    Livewire::test(App\Livewire\Painel\Equipe\Index::class)
        ->set('busca', 'Bruno')
        ->assertSee('Bruno Prof')->assertDontSee('Ana Recep');

    Livewire::test(App\Livewire\Painel\Equipe\Index::class)
        ->call('pedirInativar', $bruno->id)->assertSet('confirmarId', $bruno->id)
        ->call('inativar', $bruno->id);
    expect($bruno->fresh()->ativo)->toBeFalse();
});

it('bloqueios: remover passa por modal de confirmação', function () {
    $prof = usuarioComPapel('Profissional', ['email' => 'p@cad.test', 'e_profissional' => true]);
    $b = Bloqueio::create([
        'user_id' => $prof->id,
        'inicio' => Carbon::tomorrow()->setTime(9, 0),
        'fim' => Carbon::tomorrow()->setTime(10, 0),
        'motivo' => 'Folga',
    ]);

    Livewire::test(App\Livewire\Painel\Bloqueios\Index::class)
        ->assertDontSeeHtml('wire:confirm')
        ->call('pedirExcluir', $b->id)->assertSet('confirmarId', $b->id)
        ->call('excluir', $b->id)->assertSet('confirmarId', null);

    expect(Bloqueio::find($b->id))->toBeNull();
});

it('cadastros mostram estado vazio temático quando não há registros', function () {
    Unidade::query()->delete(); // remove a unidade do setup

    Livewire::test(App\Livewire\Painel\Unidades\Index::class)->assertSee('Nenhuma unidade cadastrada');
    Livewire::test(App\Livewire\Painel\Servicos\Index::class)->assertSee('Nenhum serviço encontrado');
    Livewire::test(App\Livewire\Painel\Bloqueios\Index::class)->assertSee('Nenhum bloqueio');
});
