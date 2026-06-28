<?php

declare(strict_types=1);

use App\Models\GatewayPagamento;
use App\Models\Tenant;
use App\Services\Pagamentos\ConexaoGatewayMercadoPago;
use App\Services\Pagamentos\PagamentoGatewayException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
| Gateway do tenant — Modelo A (direto pro dono), Fatia G1 (D78). OAuth do Mercado
| Pago MOCKADO. Cobre: state anti-CSRF, troca do code + token CIFRADO no cofre,
| desconectar, gating, e pendência de credenciais. O "conectar de verdade" depende
| das credenciais reais (client_id/secret) — fora deste teste.
*/
beforeEach(function () {
    $this->tenant = criarTenant('lojagw');

    config([
        'pagamentos.mercadopago.client_id' => 'CID',
        'pagamentos.mercadopago.client_secret' => 'SEC',
        'pagamentos.mercadopago.redirect_uri' => 'http://nextgest.test/oauth/mercadopago/callback',
        'pagamentos.mercadopago.auth_url' => 'http://mp.test/authorization',
        'pagamentos.mercadopago.token_url' => 'http://mp.test/oauth/token',
        'pagamentos.mercadopago.api_url' => 'http://mp.test',
    ]);

    Tenant::find('lojagw')->update(['recursos' => ['gateway']]);
});

function fakeMpOauthOk(): void
{
    Http::fake([
        'mp.test/oauth/token' => Http::response([
            'access_token' => 'APP_USR-TOKEN-SECRETO', 'refresh_token' => 'TG-REFRESH',
            'user_id' => 123456, 'public_key' => 'APP_USR-PUB', 'expires_in' => 21600, 'scope' => 'read write',
        ], 200),
        'mp.test/users/me' => Http::response(['id' => 123456, 'nickname' => 'SALAO_TESTE', 'email' => 's@t.test'], 200),
    ]);
}

it('iniciar gera a URL de autorização com state e guarda o nonce na sessão', function () {
    $url = app(ConexaoGatewayMercadoPago::class)->iniciar('lojagw');

    $nonce = session('mp_oauth')['nonce'];
    expect($nonce)->not->toBeEmpty()
        ->and($url)->toContain('http://mp.test/authorization')
        ->and($url)->toContain('client_id=CID')
        ->and($url)->toContain('state='.urlencode(base64_encode('lojagw|'.$nonce)));
});

it('concluir REJEITA state que não bate com a sessão (anti-CSRF) e não troca nada', function () {
    Http::fake();
    app(ConexaoGatewayMercadoPago::class)->iniciar('lojagw'); // sessão com o nonce certo

    $stateForjado = base64_encode('lojagw|nonce-do-atacante');

    expect(fn () => app(ConexaoGatewayMercadoPago::class)->concluir('CODE', $stateForjado))
        ->toThrow(PagamentoGatewayException::class);

    Http::assertNothingSent();
});

it('concluir com state válido troca o code e grava o token CIFRADO no cofre do tenant', function () {
    fakeMpOauthOk();
    $svc = app(ConexaoGatewayMercadoPago::class);
    $svc->iniciar('lojagw');
    $state = base64_encode('lojagw|'.session('mp_oauth')['nonce']);

    expect($svc->concluir('CODE', $state))->toBe('lojagw');

    tenancy()->initialize($this->tenant);
    $cfg = GatewayPagamento::where('provedor', 'mercadopago')->first();
    expect($cfg->ativo)->toBeTrue()
        ->and($cfg->conta_externa_id)->toBe('123456')
        ->and($cfg->conta_externa_nome)->toBe('SALAO_TESTE')
        ->and($cfg->credenciais['access_token'])->toBe('APP_USR-TOKEN-SECRETO'); // decifrado em memória

    // Cru no banco: CIFRADO (token não aparece em texto).
    $raw = DB::table('gateways_pagamento')->value('credenciais');
    expect($raw)->not->toContain('APP_USR-TOKEN-SECRETO');

    expect($svc->conectado())->toBeTrue();
});

it('desconectar limpa a credencial', function () {
    fakeMpOauthOk();
    $svc = app(ConexaoGatewayMercadoPago::class);
    $svc->iniciar('lojagw');
    $svc->concluir('CODE', base64_encode('lojagw|'.session('mp_oauth')['nonce']));

    tenancy()->initialize($this->tenant);
    expect($svc->conectado())->toBeTrue();

    $svc->desconectar();
    expect($svc->conectado())->toBeFalse()
        ->and(GatewayPagamento::where('provedor', 'mercadopago')->first()->credenciais)->toBeNull();
});

it('sem credenciais OAuth (placeholders) → iniciar avisa pendência, não inventa', function () {
    config(['pagamentos.mercadopago.client_id' => '', 'pagamentos.mercadopago.redirect_uri' => '']);

    expect(fn () => app(ConexaoGatewayMercadoPago::class)->iniciar('lojagw'))
        ->toThrow(PagamentoGatewayException::class);
});

it('callback HTTP com state válido conecta e redireciona p/ a tela do tenant', function () {
    fakeMpOauthOk();
    $state = base64_encode('lojagw|abc123');

    $this->withSession(['mp_oauth' => ['tenant' => 'lojagw', 'nonce' => 'abc123']])
        ->get('/oauth/mercadopago/callback?code=CODE&state='.urlencode($state))
        ->assertRedirect(route('painel.pagamentos', ['tenant' => 'lojagw']));

    tenancy()->initialize($this->tenant);
    expect(GatewayPagamento::where('provedor', 'mercadopago')->first()->ativo)->toBeTrue();
});

it('callback HTTP com state inválido não conecta (anti-CSRF)', function () {
    Http::fake();
    $state = base64_encode('lojagw|nonce-certo');

    $this->withSession(['mp_oauth' => ['tenant' => 'lojagw', 'nonce' => 'OUTRO']])
        ->get('/oauth/mercadopago/callback?code=CODE&state='.urlencode($state));

    Http::assertNothingSent();
    tenancy()->initialize($this->tenant);
    expect(GatewayPagamento::where('provedor', 'mercadopago')->first())->toBeNull();
});

it('tela do gateway 404 quando o recurso gateway está desligado', function () {
    Tenant::find('lojagw')->update(['recursos' => []]);
    $dono = $this->tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@gw.com']));
    tenancy()->end();

    $this->actingAs($dono, 'web')->get('/lojagw/painel/pagamentos')->assertNotFound();
});
