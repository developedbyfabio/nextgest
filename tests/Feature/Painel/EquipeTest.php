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
        ->set('papeis', ['Profissional'])
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
        ->set('papeis', ['Recepção'])
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
        ->set('papeis', ['Recepção'])
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

it('permite MÚLTIPLOS papéis no mesmo membro (Dono + Profissional)', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->call('novo')
        ->set('name', 'Dona que Atende')
        ->set('email', 'donaatende@lojaum.com')
        ->set('papeis', ['Dono', 'Profissional'])
        ->set('password', 'senha-inicial-123')
        ->set('e_profissional', true)
        ->call('salvar')
        ->assertHasNoErrors();

    $user = User::firstWhere('email', 'donaatende@lojaum.com');
    expect($user->hasRole('Dono'))->toBeTrue()
        ->and($user->hasRole('Profissional'))->toBeTrue()
        ->and($user->e_profissional)->toBeTrue();
});

it('exige ao menos um papel (não cria membro órfão de papel)', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->call('novo')
        ->set('name', 'Sem Papel')
        ->set('email', 'sempapel@lojaum.com')
        ->set('papeis', [])
        ->set('password', 'senha-inicial-123')
        ->call('salvar')
        ->assertHasErrors('papeis');

    expect(User::firstWhere('email', 'sempapel@lojaum.com'))->toBeNull();
});

it('editar carrega o(s) papel(is) atual(is) — equipe existente não regride', function () {
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']), 'web');
    $prof = usuarioComPapel('Profissional', ['email' => 'prof@lojaum.com', 'e_profissional' => true]);

    Livewire::test(Index::class)
        ->call('editar', $prof->id)
        ->assertSet('papeis', ['Profissional'])
        ->assertSet('e_profissional', true);
});

it('NÃO deixa remover o papel Dono do último Dono ativo (trava multi-tenant)', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'unico@lojaum.com']);
    $this->actingAs($dono, 'web');

    // Tentar rebaixar o único Dono para só Profissional → bloqueado.
    Livewire::test(Index::class)
        ->call('editar', $dono->id)
        ->set('papeis', ['Profissional'])
        ->set('e_profissional', true)
        ->call('salvar')
        ->assertHasErrors('papeis');

    expect($dono->fresh()->hasRole('Dono'))->toBeTrue(); // continua Dono
});

it('NÃO deixa inativar o último Dono ativo', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'unico@lojaum.com']);
    // Um Gerente faz a ação (tem editar_usuario), para não cair na trava de auto-inativação.
    $this->actingAs(usuarioComPapel('Gerente', ['email' => 'ger@lojaum.com']), 'web');

    Livewire::test(Index::class)->call('inativar', $dono->id);

    expect($dono->fresh()->ativo)->toBeTrue();
});

it('permite remover o papel Dono quando há OUTRO Dono ativo', function () {
    $dono1 = usuarioComPapel('Dono', ['email' => 'dono1@lojaum.com']);
    $dono2 = usuarioComPapel('Dono', ['email' => 'dono2@lojaum.com']);
    $this->actingAs($dono1, 'web');

    Livewire::test(Index::class)
        ->call('editar', $dono2->id)
        ->set('papeis', ['Gerente'])
        ->call('salvar')
        ->assertHasNoErrors();

    expect($dono2->fresh()->hasRole('Dono'))->toBeFalse()
        ->and($dono2->fresh()->hasRole('Gerente'))->toBeTrue();
});
