<?php

declare(strict_types=1);

use App\Livewire\Admin\TenantDetalhe;
use App\Models\Admin;
use App\Models\User;
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

it('super-admin reseta o 2FA do Dono (limpa os campos cifrados)', function () {
    // Mantém a tenancy inicializada (como em produção cada request faz só UM run()):
    // assim o run() interno do componente RESTAURA o tenant (sem purge), em vez de
    // encerrar — evita o artefato "connection [tenant] not configured" de dois run()
    // num mesmo processo de teste. O reset em si (limpar os campos) é o que validamos.
    $tenant = criarTenant('lojaum');
    tenancy()->initialize($tenant);

    $dono = usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']);
    $dono->two_factor_secret = 'SEGREDO-DE-TESTE-XYZ';
    $dono->two_factor_recovery_codes = ['AAAAA-BBBBB'];
    $dono->two_factor_confirmed_at = now();
    $dono->save();
    $donoId = $dono->id;
    expect($dono->temDoisFatores())->toBeTrue();

    $this->actingAs(superAdmin(), 'admin');

    // Ação única (id explícito) — fiel ao wire:click do modal, que carrega o alvo.
    Livewire::test(TenantDetalhe::class, ['tenantId' => 'lojaum'])
        ->call('resetar2fa', $donoId)
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.tenant.detalhe', ['tenantId' => 'lojaum']));

    // Depois: 2FA desativado e campos limpos.
    $depois = User::find($donoId);
    expect($depois->temDoisFatores())->toBeFalse()
        ->and($depois->two_factor_secret)->toBeNull()
        ->and($depois->two_factor_recovery_codes)->toBeNull()
        ->and($depois->two_factor_confirmed_at)->toBeNull();
});

it('confirmarReset abre o modal e guarda o Dono alvo', function () {
    $tenant = criarTenant('lojaum');
    $donoId = $tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com'])->id);

    $this->actingAs(superAdmin(), 'admin');

    Livewire::test(TenantDetalhe::class, ['tenantId' => 'lojaum'])
        ->call('confirmarReset', $donoId)
        ->assertSet('resetAlvo', $donoId)
        ->assertHasNoErrors();
});

it('reset de 2FA exige super-admin (sem admin → 403 já no componente)', function () {
    criarTenant('lojaum');

    // Sem actingAs admin: o componente (gate em mount E nas ações) responde 403.
    Livewire::test(TenantDetalhe::class, ['tenantId' => 'lojaum'])
        ->assertForbidden();
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
