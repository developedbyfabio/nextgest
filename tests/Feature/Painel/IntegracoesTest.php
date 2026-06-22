<?php

declare(strict_types=1);

use App\Livewire\Painel\Integracoes\MercadoPago;
use App\Livewire\Painel\Integracoes\Whatsapp;
use App\Models\GatewayPagamento;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappConfig;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
| Fase 0b — credenciais de integração por tenant (cifradas). Cofres REUSADOS:
| mercadopago → gateways_pagamento; whatsapp → whatsapp_config. Testes de EFEITO,
| incluindo fluxo HTTP autenticado por tenant (gating por flag 0a + permissão).
| Toggle de flag = um request por teste (gotcha 0a: initialize() mantém tenant stale).
*/

function ligarRecursos(string $tenantId, array $recursos): void
{
    $t = Tenant::find($tenantId);
    $t->recursos = $recursos;
    $t->save();
}

it('Mercado Pago: salva o token cifrado e o lê decifrado em memória', function () {
    tenancy()->initialize(criarTenant('lojaum'));
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']), 'web');

    Livewire::test(MercadoPago::class)
        ->set('access_token', 'TEST-1234567890')
        ->set('ativo', true)
        ->call('salvar')
        ->assertHasNoErrors();

    // Decifrado em memória.
    $g = GatewayPagamento::where('provedor', 'mercadopago')->first();
    expect($g->credenciais['access_token'])->toBe('TEST-1234567890')
        ->and($g->ativo)->toBeTrue();

    // Cru no banco: CIFRADO (não dá pra ler o token).
    $cru = DB::table('gateways_pagamento')->where('provedor', 'mercadopago')->value('credenciais');
    expect($cru)->not->toContain('TEST-1234567890')
        ->and($cru)->not->toBe('TEST-1234567890');

    tenancy()->end();
});

it('write-only: salvar com segredo vazio MANTÉM; preenchido SUBSTITUI', function () {
    tenancy()->initialize(criarTenant('lojaum'));
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']), 'web');

    Livewire::test(MercadoPago::class)->set('access_token', 'TOKEN-AAA-1111')->set('ativo', true)->call('salvar');

    // Recarrega vazio (write-only) e salva mexendo só no 'ativo' → segredo intacto.
    Livewire::test(MercadoPago::class)
        ->assertSet('access_token', '')
        ->set('ativo', false)
        ->call('salvar');
    expect(GatewayPagamento::where('provedor', 'mercadopago')->first()->credenciais['access_token'])->toBe('TOKEN-AAA-1111');

    // Preenche → substitui.
    Livewire::test(MercadoPago::class)->set('access_token', 'TOKEN-BBB-2222')->call('salvar');
    expect(GatewayPagamento::where('provedor', 'mercadopago')->first()->credenciais['access_token'])->toBe('TOKEN-BBB-2222');

    tenancy()->end();
});

it('a tela nunca renderiza o segredo em claro; mostra só a máscara', function () {
    tenancy()->initialize(criarTenant('lojaum'));
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']), 'web');

    Livewire::test(MercadoPago::class)->set('access_token', 'SEGREDO-XYZ-7890')->call('salvar');

    Livewire::test(MercadoPago::class)
        ->assertSet('access_token', '')
        ->assertSet('configurado', true)
        ->assertSee('••••7890')
        ->assertDontSee('SEGREDO-XYZ-7890');

    tenancy()->end();
});

it('flag gateway OFF: índice sem card e editor dá 404 (HTTP autenticado por tenant)', function () {
    $tenant = criarTenant('lojaum');
    $tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']));
    $dono = $tenant->run(fn () => User::where('email', 'dono@lojaum.com')->first());
    // gateway desligado (default)

    $this->actingAs($dono, 'web')
        ->get('/lojaum/painel/integracoes')
        ->assertOk()
        ->assertSee('Nenhuma integração disponível')
        ->assertDontSee('Mercado Pago');

    $this->actingAs($dono, 'web')
        ->get('/lojaum/painel/integracoes/mercadopago')
        ->assertNotFound();
});

it('flag gateway ON: índice mostra o card e o editor abre (HTTP autenticado por tenant)', function () {
    $tenant = criarTenant('lojaum');
    $tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']));
    $dono = $tenant->run(fn () => User::where('email', 'dono@lojaum.com')->first());
    ligarRecursos('lojaum', ['gateway']);

    $this->actingAs($dono, 'web')
        ->get('/lojaum/painel/integracoes')
        ->assertOk()
        ->assertSee('Mercado Pago')
        ->assertDontSee('Nenhuma integração disponível');

    $this->actingAs($dono, 'web')
        ->get('/lojaum/painel/integracoes/mercadopago')
        ->assertOk()
        ->assertSee('Access Token');
});

it('isolamento: credencial de um tenant não aparece em outro', function () {
    tenancy()->initialize(criarTenant('lojaa'));
    usuarioComPapel('Dono', ['email' => 'dono@a.com']);
    GatewayPagamento::create([
        'provedor' => 'mercadopago',
        'credenciais' => ['access_token' => 'TOKEN-DO-A'],
        'ativo' => true,
        'padrao' => true,
    ]);
    tenancy()->end();

    tenancy()->initialize(criarTenant('lojab'));
    expect(GatewayPagamento::where('provedor', 'mercadopago')->first())->toBeNull();
    tenancy()->end();
});

it('papel sem permissão de integração não acessa a tela nem o editor', function () {
    $tenant = criarTenant('lojaum');
    $tenant->run(fn () => usuarioComPapel('Recepção', ['email' => 'recep@lojaum.com']));
    $recep = $tenant->run(fn () => User::where('email', 'recep@lojaum.com')->first());
    ligarRecursos('lojaum', ['gateway']); // mesmo com flag on, sem permissão = 403

    $this->actingAs($recep, 'web')
        ->get('/lojaum/painel/integracoes')
        ->assertForbidden();

    $this->actingAs($recep, 'web')
        ->get('/lojaum/painel/integracoes/mercadopago')
        ->assertForbidden();
});

it('WhatsApp: token salvo cifrado; config não-secreta volta preenchida; write-only', function () {
    tenancy()->initialize(criarTenant('lojaum'));
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']), 'web');

    Livewire::test(Whatsapp::class)
        ->set('telefone', '+55 11 90000-0000')
        ->set('phone_number_id', 'PN-123')
        ->set('token', 'WTOKEN-SECRETO-4321')
        ->set('ativo', true)
        ->call('salvar')
        ->assertHasNoErrors();

    $c = WhatsappConfig::query()->first();
    expect($c->token)->toBe('WTOKEN-SECRETO-4321')
        ->and($c->phone_number_id)->toBe('PN-123');

    $cru = DB::table('whatsapp_config')->value('token');
    expect($cru)->not->toContain('WTOKEN-SECRETO-4321');

    // Write-only: recarrega vazio; config não-secreta volta; salvar sem token mantém.
    Livewire::test(Whatsapp::class)
        ->assertSet('token', '')
        ->assertSet('phone_number_id', 'PN-123')
        ->assertDontSee('WTOKEN-SECRETO-4321')
        ->set('telefone', '+55 11 91111-1111')
        ->call('salvar');
    expect(WhatsappConfig::query()->first()->token)->toBe('WTOKEN-SECRETO-4321');

    tenancy()->end();
});
