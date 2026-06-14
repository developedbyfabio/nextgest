<?php

declare(strict_types=1);

use App\Livewire\Auth\ClienteLogin;
use App\Livewire\Auth\ClienteRegistrar;
use App\Models\Cliente;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);
});

it('registra e autentica um novo cliente', function () {
    Livewire::test(ClienteRegistrar::class)
        ->set('nome', 'Maria')
        ->set('telefone', '11999998888')
        ->set('email', 'maria@cliente.com')
        ->set('password', 'senha-cliente-12345')
        ->set('password_confirmation', 'senha-cliente-12345')
        ->call('registrar')
        ->assertHasNoErrors()
        ->assertRedirect(route('tenant.home', ['tenant' => 'lojaum']));

    $this->assertAuthenticated('cliente');
    expect(Cliente::where('email', 'maria@cliente.com')->exists())->toBeTrue();
});

it('exige telefone no registro', function () {
    Livewire::test(ClienteRegistrar::class)
        ->set('nome', 'Maria')
        ->set('telefone', '')
        ->set('email', 'maria@cliente.com')
        ->set('password', 'senha-cliente-12345')
        ->set('password_confirmation', 'senha-cliente-12345')
        ->call('registrar')
        ->assertHasErrors('telefone');

    $this->assertGuest('cliente');
});

it('não permite e-mail duplicado no registro', function () {
    Cliente::create([
        'nome' => 'João',
        'telefone' => '11888887777',
        'email' => 'maria@cliente.com',
        'password' => 'qualquer-coisa-123',
    ]);

    Livewire::test(ClienteRegistrar::class)
        ->set('nome', 'Maria')
        ->set('telefone', '11999998888')
        ->set('email', 'maria@cliente.com')
        ->set('password', 'senha-cliente-12345')
        ->set('password_confirmation', 'senha-cliente-12345')
        ->call('registrar')
        ->assertHasErrors('email');
});

it('autentica um cliente existente', function () {
    $cliente = Cliente::create([
        'nome' => 'Maria',
        'telefone' => '11999998888',
        'email' => 'maria@cliente.com',
        'password' => 'senha-cliente-12345',
    ]);

    Livewire::test(ClienteLogin::class)
        ->set('email', 'maria@cliente.com')
        ->set('password', 'senha-cliente-12345')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('tenant.home', ['tenant' => 'lojaum']));

    $this->assertAuthenticatedAs($cliente, 'cliente');
});

it('rejeita senha incorreta do cliente', function () {
    Cliente::create([
        'nome' => 'Maria',
        'telefone' => '11999998888',
        'email' => 'maria@cliente.com',
        'password' => 'senha-cliente-12345',
    ]);

    Livewire::test(ClienteLogin::class)
        ->set('email', 'maria@cliente.com')
        ->set('password', 'errada')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest('cliente');
});
