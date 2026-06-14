<?php

declare(strict_types=1);

use App\Livewire\Painel\Equipe\Index;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);
});

it('cria um profissional completo (papel, serviços, unidade)', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    $unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);

    Livewire::test(Index::class)
        ->call('novo')
        ->set('name', 'Jorge Barbeiro')
        ->set('email', 'jorge@lojaum.com')
        ->set('papel', 'Profissional')
        ->set('password', 'senha-inicial-123')
        ->set('e_profissional', true)
        ->set('unidades', [$unidade->id])
        ->set('servicos', [$servico->id])
        ->call('salvar')
        ->assertHasNoErrors();

    $user = User::firstWhere('email', 'jorge@lojaum.com');
    expect($user)->not->toBeNull();
    expect($user->hasRole('Profissional'))->toBeTrue();
    expect($user->e_profissional)->toBeTrue();
    expect($user->servicos->pluck('id')->all())->toBe([$servico->id]);
    expect($user->unidades->pluck('id')->all())->toBe([$unidade->id]);
});

it('exige senha ao criar membro', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->call('novo')
        ->set('name', 'Sem Senha')
        ->set('email', 'semsenha@lojaum.com')
        ->set('papel', 'Recepção')
        ->set('password', '')
        ->call('salvar')
        ->assertHasErrors('password');
});

it('não permite e-mail duplicado na equipe', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    User::create(['name' => 'Existente', 'email' => 'dup@lojaum.com', 'password' => 'x12345678', 'ativo' => true]);

    Livewire::test(Index::class)
        ->call('novo')
        ->set('name', 'Novo')
        ->set('email', 'dup@lojaum.com')
        ->set('papel', 'Recepção')
        ->set('password', 'senha-inicial-123')
        ->call('salvar')
        ->assertHasErrors('email');
});

it('não deixa o usuário inativar a própria conta', function () {
    $dono = usuarioComPapel('Dono');
    $this->actingAs($dono, 'web');

    Livewire::test(Index::class)->call('inativar', $dono->id);

    expect(User::find($dono->id)->ativo)->toBeTrue();
});

it('bloqueia Recepção (403) na página de equipe', function () {
    $this->actingAs(usuarioComPapel('Recepção'), 'web')
        ->get('/lojaum/painel/equipe')
        ->assertForbidden();
});
