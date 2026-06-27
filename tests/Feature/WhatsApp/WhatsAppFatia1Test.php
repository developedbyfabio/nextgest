<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\WhatsappConfig;
use App\Services\WhatsApp\EvolutionGateway;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/*
| WhatsApp Fatia 1 (D75) — WhatsAppService + driver Evolution. A Evolution é MOCKADA
| (Http::fake); o ponta-a-ponta real é validado à mão com o Fabio. Cobre: chamada
| correta à instância do tenant, key da instância vs global, normalização de número,
| erro tratado (sem 500), gating do recurso, e segredo cifrado/sem vazar.
*/
beforeEach(function () {
    $this->tenant = criarTenant('lojawa');
    tenancy()->initialize($this->tenant);

    config([
        'whatsapp.base_url' => 'http://evo.test',
        'whatsapp.api_key' => 'GLOBALKEY',
        'whatsapp.timeout' => 5,
        'whatsapp.prefixo_instancia' => 'ng_',
    ]);

    // Liga o recurso whatsapp no registro central.
    $this->tenant->recursos = ['whatsapp'];
    $this->tenant->save();
});

it('envia texto na instância do tenant, com número normalizado e a key DA INSTÂNCIA', function () {
    WhatsappConfig::create(['instancia' => 'ng_lojawa', 'instancia_token' => 'INSTTOKEN', 'ativo' => true]);
    Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'MSG123'], 'status' => 'PENDING'], 201)]);

    $r = app(WhatsAppService::class)->enviarTexto('(11) 99999-8888', 'Olá!');

    expect($r['id'])->toBe('MSG123')->and($r['status'])->toBe('PENDING');

    Http::assertSent(function ($request) {
        return $request->url() === 'http://evo.test/message/sendText/ng_lojawa'
            && $request['number'] === '5511999998888'
            && $request['text'] === 'Olá!'
            && $request->hasHeader('apikey', 'INSTTOKEN'); // token da instância, não a global
    });
});

it('usa a key GLOBAL quando a instância não tem token próprio', function () {
    WhatsappConfig::create(['instancia' => 'ng_lojawa', 'ativo' => true]); // sem instancia_token
    Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'X'], 'status' => 'PENDING'], 201)]);

    app(WhatsAppService::class)->enviarTexto('11999998888', 'oi');

    Http::assertSent(fn ($request) => $request->hasHeader('apikey', 'GLOBALKEY'));
});

it('normaliza número BR: prefixa 55, não duplica DDI, exige dígitos', function () {
    $g = new EvolutionGateway;

    expect($g->normalizarNumero('(11) 99999-8888'))->toBe('5511999998888')
        ->and($g->normalizarNumero('5511999998888'))->toBe('5511999998888')   // já com DDI, não duplica
        ->and($g->normalizarNumero('+55 11 99999-8888'))->toBe('5511999998888');

    expect(fn () => $g->normalizarNumero('abc'))->toThrow(WhatsAppException::class);
});

it('trata falha/timeout da Evolution como WhatsAppException (não derruba com 500)', function () {
    WhatsappConfig::create(['instancia' => 'ng_lojawa', 'ativo' => true]);
    Http::fake(['evo.test/*' => Http::response(['message' => 'boom'], 500)]);

    expect(fn () => app(WhatsAppService::class)->enviarTexto('11999998888', 'oi'))
        ->toThrow(WhatsAppException::class);
});

it('exige a instância configurada antes de enviar', function () {
    // Sem WhatsappConfig.instancia → erro tratado.
    expect(fn () => app(WhatsAppService::class)->enviarTexto('11999998888', 'oi'))
        ->toThrow(WhatsAppException::class);
});

it('comando whatsapp-teste envia e retorna 0', function () {
    WhatsappConfig::create(['instancia' => 'ng_lojawa', 'instancia_token' => 'INSTTOKEN', 'ativo' => true]);
    Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'OK'], 'status' => 'PENDING'], 201)]);

    $this->artisan('nextgest:whatsapp-teste', ['tenant' => 'lojawa', 'numero' => '11999998888'])
        ->assertExitCode(0);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/message/sendText/ng_lojawa'));
});

it('comando whatsapp-teste é bloqueado quando o recurso whatsapp está off', function () {
    Tenant::find('lojawa')->update(['recursos' => []]); // desliga o recurso (central)
    WhatsappConfig::create(['instancia' => 'ng_lojawa', 'ativo' => true]);
    Http::fake(); // nada deve ser chamado

    $this->artisan('nextgest:whatsapp-teste', ['tenant' => 'lojawa', 'numero' => '11999998888'])
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('token da instância é cifrado no banco e não vaza em serialização; a key global não está no tenant', function () {
    WhatsappConfig::create(['instancia' => 'ng_lojawa', 'instancia_token' => 'SEGREDO_INST', 'ativo' => true]);

    // No banco, o valor está cifrado (não é o texto puro).
    $cru = DB::table('whatsapp_config')->value('instancia_token');
    expect($cru)->not->toBe('SEGREDO_INST')->and($cru)->not->toBeNull();

    // Em array/JSON, o segredo não aparece ($hidden).
    $arr = WhatsappConfig::first()->toArray();
    expect($arr)->not->toHaveKey('instancia_token')->not->toHaveKey('token');

    // A key GLOBAL vem do config (.env), nunca de uma coluna do tenant.
    expect(config('whatsapp.api_key'))->toBe('GLOBALKEY');
    expect(Schema::hasColumn('whatsapp_config', 'api_key'))->toBeFalse();
});
