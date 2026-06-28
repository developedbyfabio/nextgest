<?php

declare(strict_types=1);

use App\Livewire\Painel\Whatsapp\Automacoes;
use App\Livewire\Painel\Whatsapp\Janela;
use App\Livewire\Painel\Whatsapp\OptOut;
use App\Models\Cliente;
use App\Models\WhatsappConfig;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
| WhatsApp melhorias de UI/UX (D84). Só interação/persistência — a lógica de envio/janela/
| aquecimento não muda. Cobre: número de teste persistente por tenant; validação → toast +
| evento de foco (sem salvar); confirmação de "voltar a enviar" do opt-out (modal D65).
*/
beforeEach(function () {
    $this->tenant = criarTenant('lojaui');
    tenancy()->initialize($this->tenant);

    config(['whatsapp.base_url' => 'http://evo.test', 'whatsapp.api_key' => 'GLOBALKEY']);
    $this->tenant->recursos = ['whatsapp'];
    $this->tenant->save();

    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@ui.test']), 'web');
    WhatsappConfig::create(['instancia' => 'ng_lojaui', 'status_conexao' => 'open']);
});

it('número de teste persiste por tenant (salvar) e pré-preenche ao reabrir', function () {
    Livewire::test(Automacoes::class)
        ->set('numeroTeste', '41999998888')
        ->call('salvar');

    expect(WhatsappConfig::first()->numero_teste)->toBe('41999998888');

    // Reabrir a tela já vem com o número salvo.
    Livewire::test(Automacoes::class)->assertSet('numeroTeste', '41999998888');
});

it('o botão testar também memoriza o número usado', function () {
    Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'X']], 201)]);

    Livewire::test(Automacoes::class)
        ->set('numeroTeste', '41888887777')
        ->call('testar', 'lembrete_servico');

    expect(WhatsappConfig::first()->numero_teste)->toBe('41888887777');
});

it('validação inválida na Janela dispara o evento de foco, mostra erro e NÃO salva', function () {
    Livewire::test(Janela::class)
        ->set('globalInicio', '20:00')
        ->set('globalFim', '08:00') // fim <= início → inválido
        ->call('salvar')
        ->assertHasErrors('globalFim')
        ->assertDispatched('wa-erro-validacao');

    // Não persistiu a janela inválida.
    expect(WhatsappConfig::first()->janela)->toBeNull();
});

it('validação OK na Janela não dispara o evento de foco e salva', function () {
    Livewire::test(Janela::class)
        ->set('globalInicio', '09:00')
        ->set('globalFim', '18:00')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertNotDispatched('wa-erro-validacao');

    expect(WhatsappConfig::first()->janela['inicio'])->toBe('09:00')
        ->and(WhatsappConfig::first()->janela['fim'])->toBe('18:00');
});

it('opt-out: confirmarRemocao prepara o modal e desmarcar tira do opt-out', function () {
    $cli = Cliente::create(['nome' => 'Rosa', 'telefone' => '4191', 'email' => 'rosa@ui.test', 'whatsapp_optout' => true]);

    $comp = Livewire::test(OptOut::class)
        ->call('confirmarRemocao', $cli->id)
        ->assertSet('confirmarId', $cli->id)
        ->assertSet('confirmarNome', 'Rosa');

    $comp->call('desmarcar', $cli->id)->assertSet('confirmarId', null);

    expect(Cliente::find($cli->id)->whatsapp_optout)->toBeFalse();
});
