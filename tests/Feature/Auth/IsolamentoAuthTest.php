<?php

declare(strict_types=1);

use App\Livewire\Auth\PainelLogin;
use App\Models\Admin;
use App\Models\Cliente;
use App\Models\User;
use Livewire\Livewire;

it('cliente autenticado não acessa o painel da equipe', function () {
    $tenant = criarTenant('lojaum');
    tenancy()->initialize($tenant);
    $cliente = Cliente::create([
        'nome' => 'Maria',
        'telefone' => '11999998888',
        'email' => 'maria@cliente.com',
        'password' => 'senha-cliente-12345',
    ]);
    tenancy()->end();

    $this->actingAs($cliente, 'cliente')
        ->get('/lojaum/painel')
        ->assertRedirect(route('painel.login', ['tenant' => 'lojaum']));
});

it('login de um tenant não vale em outro', function () {
    $a = criarTenant('lojaum');
    tenancy()->initialize($a);
    User::create([
        'name' => 'Dono A',
        'email' => 'dono@lojaum.com',
        'password' => 'senha-equipe-12345',
        'ativo' => true,
    ]);
    tenancy()->end();

    $b = criarTenant('lojadois');
    tenancy()->initialize($b);

    // Credenciais válidas no tenant A não devem autenticar no tenant B.
    Livewire::test(PainelLogin::class)
        ->set('email', 'dono@lojaum.com')
        ->set('password', 'senha-equipe-12345')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest('web');
});

it('login de admin não autentica equipe nem cliente', function () {
    $admin = Admin::create([
        'name' => 'Super',
        'email' => 'super@nextgest.com.br',
        'password' => 'senha-super-12345',
        'ativo' => true,
    ]);

    $this->actingAs($admin, 'admin');

    $this->assertAuthenticatedAs($admin, 'admin');
    $this->assertGuest('web');
    $this->assertGuest('cliente');
});
