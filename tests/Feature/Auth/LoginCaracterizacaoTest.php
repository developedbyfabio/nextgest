<?php

declare(strict_types=1);

use App\Livewire\Auth\AdminLogin;
use App\Livewire\Auth\ClienteLogin;
use App\Livewire\Auth\PainelLogin;
use App\Models\Cliente;
use Livewire\Livewire;

/*
| CARACTERIZAÇÃO do login SÓ-SENHA (âncora de não-regressão do 2FA).
| Pina o comportamento ANTES do ramo de 2FA: quem NÃO tem 2FA loga exatamente como
| hoje, e os logins de admin/cliente não mudam. Estes testes devem permanecer verdes
| depois da introdução do desafio de 2FA.
*/

it('[caract] Dono SEM 2FA loga só com senha e cai no dashboard', function () {
    tenancy()->initialize(criarTenant('caractweb'));
    $dono = usuarioComPapel('Dono', ['email' => 'dono@caractweb.test', 'password' => 'senha-do-dono-123']);

    Livewire::test(PainelLogin::class)
        ->set('email', 'dono@caractweb.test')
        ->set('password', 'senha-do-dono-123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('painel.dashboard', ['tenant' => 'caractweb']));

    $this->assertAuthenticatedAs($dono, 'web');
});

it('[caract] membro comum (Recepção) loga só com senha', function () {
    tenancy()->initialize(criarTenant('caractweb2'));
    $u = usuarioComPapel('Recepção', ['email' => 'rec@caractweb2.test', 'password' => 'senha-da-rec-123']);

    Livewire::test(PainelLogin::class)
        ->set('email', 'rec@caractweb2.test')
        ->set('password', 'senha-da-rec-123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('painel.dashboard', ['tenant' => 'caractweb2']));

    $this->assertAuthenticatedAs($u, 'web');
});

it('[caract] super-admin loga só com senha (guard admin, inalterado)', function () {
    $a = admin();

    Livewire::test(AdminLogin::class)
        ->set('email', $a->email)
        ->set('password', 'senha-super-12345')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($a, 'admin');
});

it('[caract] cliente loga só com senha (guard cliente, inalterado)', function () {
    tenancy()->initialize(criarTenant('caractcli'));
    $cli = Cliente::create([
        'nome' => 'Cliente Teste',
        'email' => 'cli@caractcli.test',
        'telefone' => '11999990000',
        'password' => 'senha-do-cli-123',
    ]);

    Livewire::test(ClienteLogin::class)
        ->set('email', 'cli@caractcli.test')
        ->set('password', 'senha-do-cli-123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('tenant.home', ['tenant' => 'caractcli']));

    $this->assertAuthenticatedAs($cli, 'cliente');
});
