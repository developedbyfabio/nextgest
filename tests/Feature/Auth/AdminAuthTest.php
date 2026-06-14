<?php

declare(strict_types=1);

use App\Livewire\Auth\AdminLogin;
use App\Models\Admin;
use Livewire\Livewire;

function criarAdmin(array $attrs = []): Admin
{
    return Admin::create(array_merge([
        'name' => 'Super Admin',
        'email' => 'super@nextgest.com.br',
        'password' => 'senha-super-12345',
        'ativo' => true,
    ], $attrs));
}

it('exibe a tela de login do admin', function () {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('Acesso administrativo');
});

it('autentica um admin com credenciais válidas', function () {
    $admin = criarAdmin();

    Livewire::test(AdminLogin::class)
        ->set('email', 'super@nextgest.com.br')
        ->set('password', 'senha-super-12345')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin, 'admin');
});

it('rejeita senha incorreta sem revelar a causa', function () {
    criarAdmin();

    Livewire::test(AdminLogin::class)
        ->set('email', 'super@nextgest.com.br')
        ->set('password', 'errada')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest('admin');
});

it('não autentica admin inativo', function () {
    criarAdmin(['email' => 'inativo@nextgest.com.br', 'ativo' => false]);

    Livewire::test(AdminLogin::class)
        ->set('email', 'inativo@nextgest.com.br')
        ->set('password', 'senha-super-12345')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest('admin');
});

it('bloqueia por throttle após 5 tentativas falhas', function () {
    criarAdmin();

    foreach (range(1, 5) as $i) {
        Livewire::test(AdminLogin::class)
            ->set('email', 'super@nextgest.com.br')
            ->set('password', 'errada')
            ->call('login')
            ->assertHasErrors('email');
    }

    // Mesmo com a senha correta, a 6ª tentativa é barrada pelo throttle.
    Livewire::test(AdminLogin::class)
        ->set('email', 'super@nextgest.com.br')
        ->set('password', 'senha-super-12345')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest('admin');
});

it('faz logout do admin', function () {
    $admin = criarAdmin();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.logout'))
        ->assertRedirect(route('admin.login'));

    $this->assertGuest('admin');
});
