<?php

declare(strict_types=1);

use App\Livewire\Auth\PainelLogin;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);
});

function criarMembro(array $attrs = []): User
{
    return User::create(array_merge([
        'name' => 'Jorge',
        'email' => 'jorge@lojaum.com',
        'password' => 'senha-equipe-12345',
        'e_profissional' => true,
        'ativo' => true,
    ], $attrs));
}

it('exibe a tela de login da equipe', function () {
    $this->get(route('painel.login', ['tenant' => 'lojaum']))
        ->assertOk()
        ->assertSee('Acesso da equipe');
});

it('autentica um membro da equipe', function () {
    $membro = criarMembro();

    Livewire::test(PainelLogin::class)
        ->set('email', 'jorge@lojaum.com')
        ->set('password', 'senha-equipe-12345')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('painel.dashboard', ['tenant' => 'lojaum']));

    $this->assertAuthenticatedAs($membro, 'web');
});

it('rejeita senha incorreta da equipe', function () {
    criarMembro();

    Livewire::test(PainelLogin::class)
        ->set('email', 'jorge@lojaum.com')
        ->set('password', 'errada')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest('web');
});

it('não autentica membro inativo', function () {
    criarMembro(['email' => 'inativo@lojaum.com', 'ativo' => false]);

    Livewire::test(PainelLogin::class)
        ->set('email', 'inativo@lojaum.com')
        ->set('password', 'senha-equipe-12345')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest('web');
});
