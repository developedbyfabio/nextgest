<?php

declare(strict_types=1);

use App\Livewire\Painel\Whatsapp\Conexao;
use App\Models\Tenant;
use App\Models\WhatsappConfig;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
| WhatsApp Fatia 2 (D76) — tela de conexão (Evolution MOCKADA). Estados, status ao vivo
| (poll que para ao conectar), desconectar, erro tratado e gating por permissão.
*/
beforeEach(function () {
    $this->tenant = criarTenant('lojawa2');
    tenancy()->initialize($this->tenant);

    config(['whatsapp.base_url' => 'http://evo.test', 'whatsapp.api_key' => 'GLOBALKEY', 'whatsapp.timeout' => 5]);
    $this->tenant->recursos = ['whatsapp'];
    $this->tenant->save();

    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@wa2.com']), 'web');
});

it('conectar gera o QR e entra em "aguardando"', function () {
    Http::fake([
        'evo.test/instance/create' => Http::response([
            'instance' => ['instanceName' => 'ng_lojawa2', 'status' => 'connecting'],
            'hash' => 'TOKEN-INST',
            'qrcode' => ['base64' => 'data:image/png;base64,ABC', 'code' => 'c'],
        ], 201),
    ]);

    Livewire::test(Conexao::class)
        ->call('conectar')
        ->assertSet('estado', 'aguardando')
        ->assertSet('qr', 'data:image/png;base64,ABC');

    expect(WhatsappConfig::first()->instancia)->toBe('ng_lojawa2');
});

it('o status ao vivo vira "conectado" quando a instância abre (e zera o QR)', function () {
    WhatsappConfig::create(['instancia' => 'ng_lojawa2']);
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);

    Livewire::test(Conexao::class)
        ->set('estado', 'aguardando')
        ->call('verificarStatus')
        ->assertSet('estado', 'conectado')
        ->assertSet('qr', null);
});

it('sincronizar confirma o estado real no load: instância aberta → conectado', function () {
    WhatsappConfig::create(['instancia' => 'ng_lojawa2', 'status_conexao' => 'connecting']);
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);

    Livewire::test(Conexao::class)
        ->call('sincronizar')
        ->assertSet('estado', 'conectado');
});

it('monitorar detecta a queda da sessão (conectado → caiu)', function () {
    WhatsappConfig::create(['instancia' => 'ng_lojawa2', 'status_conexao' => 'open']);
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'close']], 200)]);

    Livewire::test(Conexao::class)
        ->set('estado', 'conectado')
        ->call('monitorar')
        ->assertSet('estado', 'caiu');
});

it('verificarStatus não faz nada fora do estado "aguardando" (poll só enquanto espera)', function () {
    WhatsappConfig::create(['instancia' => 'ng_lojawa2']);
    Http::fake();

    Livewire::test(Conexao::class)
        ->set('estado', 'desconectado')
        ->call('verificarStatus')
        ->assertSet('estado', 'desconectado');

    Http::assertNothingSent();
});

it('desconectar faz logout da instância e volta a "desconectado"', function () {
    WhatsappConfig::create(['instancia' => 'ng_lojawa2', 'status_conexao' => 'open']);
    Http::fake(['evo.test/instance/logout/*' => Http::response(['status' => 'SUCCESS'], 200)]);

    Livewire::test(Conexao::class)
        ->set('estado', 'conectado')
        ->call('desconectar')
        ->assertSet('estado', 'desconectado')
        ->assertSet('qr', null);

    Http::assertSent(fn ($r) => str_contains($r->url(), '/instance/logout/ng_lojawa2') && $r->method() === 'DELETE');
});

it('erro da Evolution ao conectar vira estado "erro" (sem 500)', function () {
    Http::fake(['evo.test/*' => Http::response(['message' => 'boom'], 500)]);

    Livewire::test(Conexao::class)
        ->call('conectar')
        ->assertSet('estado', 'erro')
        ->assertDispatched('toast-show');
});

// Um GET por teste; encerra a tenancy do beforeEach antes (senão o tenant fica stale e
// o GET lê o recurso antigo — gotcha 0a: initialize() mantém o tenant em cache).
it('rota /whatsapp com o recurso whatsapp LIGADO → 200', function () {
    tenancy()->end(); // beforeEach já deixou o recurso whatsapp ligado no central
    $this->get('/lojawa2/painel/whatsapp')->assertOk();
});

it('rota /whatsapp com o recurso whatsapp DESLIGADO → 404 (VerificaRecurso)', function () {
    Tenant::find('lojawa2')->update(['recursos' => []]);
    tenancy()->end();
    $this->get('/lojawa2/painel/whatsapp')->assertNotFound();
});
