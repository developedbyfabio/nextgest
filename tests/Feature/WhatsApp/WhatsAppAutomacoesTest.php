<?php

declare(strict_types=1);

use App\Enums\AutomacaoWhatsapp;
use App\Livewire\Painel\Whatsapp\Automacoes;
use App\Models\Tenant;
use App\Models\WhatsappConfig;
use App\Services\WhatsApp\RenderizadorTemplate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
| WhatsApp Fatia 3 (D77) — config de automações (on/off + template). NADA dispara:
| só persiste config + o botão "testar" (manual, dados de exemplo, reusa D75). Evolution
| mockada. Cobre: catálogo, renderizador seguro, persistência, broadcast off por padrão,
| testar (com variável inválida literal) e gating de recurso.
*/
beforeEach(function () {
    $this->tenant = criarTenant('lojaut3');
    tenancy()->initialize($this->tenant);

    config(['whatsapp.base_url' => 'http://evo.test', 'whatsapp.api_key' => 'GLOBALKEY', 'whatsapp.timeout' => 5]);
    $this->tenant->recursos = ['whatsapp'];
    $this->tenant->save();

    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@ut3.com']), 'web');
    WhatsappConfig::create(['instancia' => 'ng_lojaut3', 'status_conexao' => 'open']);
});

it('catálogo tem 3 transacionais + 3 broadcast (sensíveis)', function () {
    expect(AutomacaoWhatsapp::transacionais())->toHaveCount(3)
        ->and(AutomacaoWhatsapp::broadcasts())->toHaveCount(3)
        ->and(AutomacaoWhatsapp::Noticias->broadcast())->toBeTrue()
        ->and(AutomacaoWhatsapp::LembreteServico->broadcast())->toBeFalse();
});

it('renderizador troca variáveis conhecidas e deixa as desconhecidas LITERAIS (sem quebrar)', function () {
    $out = RenderizadorTemplate::render('Oi {cliente}, dia {data}. {xpto}', ['cliente' => 'Ana', 'data' => '01/01']);
    expect($out)->toBe('Oi Ana, dia 01/01. {xpto}');
});

it('renderizador remove caracteres de controle do valor (sem injeção)', function () {
    $out = RenderizadorTemplate::render('{x}', ['x' => "a\x00b\x07c"]);
    expect($out)->toBe('abc');
});

it('defaults: tudo desligado (broadcast off) e templates = padrão', function () {
    Livewire::test(Automacoes::class)
        ->assertSet('ativo.lembrete_servico', false)
        ->assertSet('ativo.noticias', false) // broadcast off por padrão
        ->assertSet('template.lembrete_servico', AutomacaoWhatsapp::LembreteServico->templatePadrao());
});

it('salvar persiste on/off + template no JSON whatsapp_config.automacoes', function () {
    Livewire::test(Automacoes::class)
        ->call('aceitarTermo') // D80: aceite libera a ativação
        ->set('ativo.lembrete_servico', true)
        ->set('template.lembrete_servico', 'Olá {cliente}!')
        ->call('salvar');

    $aut = WhatsappConfig::first()->automacoes;
    expect($aut['lembrete_servico']['ativo'])->toBeTrue()
        ->and($aut['lembrete_servico']['template'])->toBe('Olá {cliente}!')
        ->and($aut['noticias']['ativo'])->toBeFalse(); // broadcast permanece off
});

it('testar renderiza com dados de exemplo e envia (variável inválida fica literal)', function () {
    Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'X'], 'status' => 'PENDING'], 201)]);

    Livewire::test(Automacoes::class)
        ->set('numeroTeste', '41999998888')
        ->set('template.lembrete_servico', 'Olá {cliente}, {xpto}')
        ->call('testar', 'lembrete_servico');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/message/sendText/ng_lojaut3')
        && $r['text'] === 'Olá Maria Souza, {xpto}'); // cliente trocado; {xpto} literal
});

it('testar exige número de teste', function () {
    Http::fake();
    Livewire::test(Automacoes::class)
        ->set('numeroTeste', '')
        ->call('testar', 'lembrete_servico')
        ->assertHasErrors('numeroTeste');
    Http::assertNothingSent();
});

it('testar trata erro da Evolution sem 500 (toast de aviso)', function () {
    Http::fake(['evo.test/*' => Http::response(['message' => 'boom'], 500)]);

    Livewire::test(Automacoes::class)
        ->set('numeroTeste', '41999998888')
        ->call('testar', 'lembrete_servico')
        ->assertDispatched('toast-show');
});

// Um GET por teste; encerra a tenancy do beforeEach antes (gotcha 0a: tenant em cache).
it('rota de automações com recurso LIGADO → 200', function () {
    tenancy()->end();
    $this->get('/lojaut3/painel/whatsapp/automacoes')->assertOk();
});

it('rota de automações com recurso DESLIGADO → 404', function () {
    Tenant::find('lojaut3')->update(['recursos' => []]);
    tenancy()->end();
    $this->get('/lojaut3/painel/whatsapp/automacoes')->assertNotFound();
});
