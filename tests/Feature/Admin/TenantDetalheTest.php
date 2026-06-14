<?php

declare(strict_types=1);

use App\Livewire\Admin\TenantDetalhe;
use App\Models\Admin;
use Livewire\Livewire;
use Stancl\Tenancy\Database\Models\ImpersonationToken;

function superAdmin(): Admin
{
    return Admin::create([
        'name' => 'Super',
        'email' => 'super@nextgest.com.br',
        'password' => 'senha-super-12345',
        'ativo' => true,
    ]);
}

afterEach(function () {
    foreach (glob(database_path('tenant_*')) as $arquivo) {
        @unlink($arquivo);
    }
});

it('exibe o detalhe do estabelecimento para o super-admin', function () {
    $tenant = criarTenant('lojaum');
    $tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']));

    $this->actingAs(superAdmin(), 'admin')
        ->get('/admin/estabelecimentos/lojaum')
        ->assertOk()
        ->assertSee('Lojaum')
        ->assertSee('Donos')
        ->assertSee('dono@lojaum.com');
});

it('bloqueia não-admin no detalhe do estabelecimento', function () {
    criarTenant('lojaum');

    $this->get('/admin/estabelecimentos/lojaum')
        ->assertRedirect(route('admin.login'));
});

it('gera token de impersonação e redireciona', function () {
    $tenant = criarTenant('lojaum');
    $tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']));

    $this->actingAs(superAdmin(), 'admin');

    Livewire::test(TenantDetalhe::class, ['tenantId' => 'lojaum'])
        ->call('impersonatar')
        ->assertRedirect();

    expect(ImpersonationToken::count())->toBe(1);
});

it('avisa quando o estabelecimento não tem dono', function () {
    criarTenant('lojaum'); // sem dono

    $this->actingAs(superAdmin(), 'admin');

    Livewire::test(TenantDetalhe::class, ['tenantId' => 'lojaum'])
        ->call('impersonatar')
        ->assertNoRedirect();

    expect(ImpersonationToken::count())->toBe(0);
});

it('entra no painel via token (impersonação) e depois sai', function () {
    $tenant = criarTenant('lojaum');
    $donoId = $tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com'])->id);

    $token = tenancy()->impersonate($tenant, (string) $donoId, route('painel.dashboard', ['tenant' => 'lojaum']), 'web');

    // Entra: loga como o Dono no contexto do tenant e consome o token.
    $this->get("/lojaum/suporte/{$token->token}")
        ->assertRedirect(route('painel.dashboard', ['tenant' => 'lojaum']));
    expect(ImpersonationToken::count())->toBe(0); // token de uso único consumido = login ok

    // Sai do suporte: encerra e volta ao /admin.
    $this->post(route('painel.suporte.sair', ['tenant' => 'lojaum']))
        ->assertRedirect(route('admin.tenants'));
    $this->assertGuest('web');
});
