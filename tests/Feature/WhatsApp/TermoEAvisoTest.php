<?php

declare(strict_types=1);

use App\Livewire\Painel\AvisoWhatsappConexao;
use App\Livewire\Painel\Whatsapp\Automacoes;
use App\Models\WhatsappConfig;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
| WhatsApp Fatia 4.5 (D80) — termo de risco (trava a ativação) + aviso de queda. Não
| dispara nada. Evolution mockada para o status.
*/
beforeEach(function () {
    $this->tenant = criarTenant('lojatermo');
    tenancy()->initialize($this->tenant);

    config(['whatsapp.base_url' => 'http://evo.test', 'whatsapp.api_key' => 'K', 'whatsapp.termo_versao' => '1']);
    $this->tenant->recursos = ['whatsapp'];
    $this->tenant->save();

    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@termo.com']), 'web');
    WhatsappConfig::create(['instancia' => 'ng_lojatermo', 'status_conexao' => 'open']);
});

it('TRAVA: sem aceitar o termo, salvar NÃO liga automação (servidor força off)', function () {
    Livewire::test(Automacoes::class)
        ->assertSet('termoAceito', false)
        ->set('ativo.lembrete_servico', true)
        ->call('salvar')
        ->assertSet('ativo.lembrete_servico', false); // reflete o que foi salvo

    expect(WhatsappConfig::first()->automacoes['lembrete_servico']['ativo'])->toBeFalse();
});

it('aceitar o termo registra quem/quando/versão e LIBERA a ativação', function () {
    Livewire::test(Automacoes::class)
        ->call('aceitarTermo')
        ->assertSet('termoAceito', true)
        ->set('ativo.lembrete_servico', true)
        ->call('salvar');

    $cfg = WhatsappConfig::first();
    expect($cfg->termo_aceito_em)->not->toBeNull()
        ->and($cfg->termo_aceito_por)->not->toBeEmpty()
        ->and($cfg->termo_versao)->toBe('1')
        ->and($cfg->termoAceito())->toBeTrue()
        ->and($cfg->automacoes['lembrete_servico']['ativo'])->toBeTrue(); // agora liga
});

it('bump da versão do termo re-exige aceite (versão antiga não vale)', function () {
    WhatsappConfig::first()->update(['termo_aceito_em' => now(), 'termo_aceito_por' => 'X', 'termo_versao' => '0']);
    config(['whatsapp.termo_versao' => '1']); // versão atual mudou

    expect(WhatsappConfig::first()->termoAceito())->toBeFalse();

    // E a trava continua valendo: salvar não liga.
    Livewire::test(Automacoes::class)
        ->set('ativo.lembrete_servico', true)
        ->call('salvar');
    expect(WhatsappConfig::first()->automacoes['lembrete_servico']['ativo'])->toBeFalse();
});

it('aviso de queda: caiu=true quando a sessão está fechada (e já conectou)', function () {
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'close']], 200)]);

    Livewire::test(AvisoWhatsappConexao::class)->call('verificar')->assertSet('caiu', true);
});

it('aviso de queda: caiu=false quando conectado (open)', function () {
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);

    Livewire::test(AvisoWhatsappConexao::class)->call('verificar')->assertSet('caiu', false);
});

it('aviso de queda: NÃO alarma quem nunca conectou (sem instância) — nem chama a Evolution', function () {
    WhatsappConfig::first()->update(['instancia' => null]);
    Http::fake();

    Livewire::test(AvisoWhatsappConexao::class)->call('verificar')->assertSet('caiu', false);
    Http::assertNothingSent();
});

it('aviso de queda: NÃO alarma sem a permissão gerenciar_whatsapp', function () {
    $this->actingAs(usuarioComPapel('Recepção', ['email' => 'rec@termo.com']), 'web');
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'close']], 200)]);

    Livewire::test(AvisoWhatsappConexao::class)->call('verificar')->assertSet('caiu', false);
    Http::assertNothingSent();
});
