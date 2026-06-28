<?php

declare(strict_types=1);

use App\Jobs\EnviarLembreteWhatsApp;
use App\Livewire\Painel\Whatsapp\OptOut;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\WhatsappConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
| Broadcast Fatia 1 (D86) — consentimento de MARKETING separado do opt-out GERAL (D83).
| Sair do marketing NÃO afeta o transacional (lembrete/avaliação). Nada dispara aqui.
*/
beforeEach(function () {
    $this->tenant = criarTenant('lojamkt');
    tenancy()->initialize($this->tenant);
    Carbon::setTestNow('2026-06-29 10:00:00');

    config([
        'whatsapp.base_url' => 'http://evo.test', 'whatsapp.api_key' => 'K',
        'whatsapp.lembretes.limite_por_minuto' => 10, 'whatsapp.lembretes.limite_por_dia' => 150,
        'whatsapp.aquecimento.ativo' => false,
    ]);
    $this->tenant->recursos = ['whatsapp'];
    $this->tenant->save();

    WhatsappConfig::create([
        'instancia' => 'ng_lojamkt', 'status_conexao' => 'open',
        'conectado_em' => now()->subDays(1), 'numero_conectado' => '55410000@s.whatsapp.net',
        'automacoes' => ['lembrete_servico' => ['ativo' => true, 'template' => 'Oi {cliente}', 'antecedencia_min' => 60]],
    ]);

    $this->unidade = Unidade::create(['nome' => 'M', 'ativo' => true]);
    $this->servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);
    $this->prof = usuarioComPapel('Profissional', ['name' => 'J', 'email' => 'j@mkt.test', 'e_profissional' => true]);
});

afterEach(fn () => Carbon::setTestNow());

function clienteMkt(bool $geral, bool $marketing): Cliente
{
    return Cliente::create([
        'nome' => 'C'.uniqid(), 'telefone' => '4199990000', 'email' => uniqid().'@mkt.test',
        'whatsapp_optout' => $geral, 'whatsapp_marketing_optout' => $marketing,
    ]);
}

function agMkt(Cliente $cli): Agendamento
{
    $self = test();
    $ini = now()->copy()->addMinutes(30);
    $ag = Agendamento::create([
        'unidade_id' => $self->unidade->id, 'cliente_id' => $cli->id, 'profissional_id' => $self->prof->id,
        'data_hora_inicio' => $ini, 'data_hora_fim' => $ini->copy()->addMinutes(30),
        'status' => 'confirmado', 'origem' => 'equipe', 'valor_total' => 40,
    ]);
    $ag->itens()->create(['servico_id' => $self->servico->id, 'preco' => 40, 'duracao_minutos' => 30]);

    return $ag;
}

it('aceitaMarketing() cobre as 4 combinações', function () {
    expect(clienteMkt(geral: false, marketing: false)->aceitaMarketing())->toBeTrue()
        ->and(clienteMkt(geral: false, marketing: true)->aceitaMarketing())->toBeFalse()
        ->and(clienteMkt(geral: true, marketing: false)->aceitaMarketing())->toBeFalse()
        ->and(clienteMkt(geral: true, marketing: true)->aceitaMarketing())->toBeFalse();
});

it('scopeAceitaMarketing filtra só quem aceita (sem opt-out geral nem de marketing)', function () {
    clienteMkt(false, false); // aceita
    clienteMkt(false, true);  // sem marketing
    clienteMkt(true, false);  // bloqueado geral
    expect(Cliente::aceitamMarketing()->count())->toBe(1);
});

it('opt-out de MARKETING não afeta o transacional: o lembrete ainda enfileira', function () {
    Queue::fake();
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);
    agMkt(clienteMkt(geral: false, marketing: true)); // fora do marketing, mas recebe transacional

    $this->artisan('nextgest:enviar-lembretes');

    Queue::assertPushed(EnviarLembreteWhatsApp::class, 1);
});

it('opt-out GERAL bloqueia o transacional (como antes, D83)', function () {
    Queue::fake();
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);
    agMkt(clienteMkt(geral: true, marketing: false));

    $this->artisan('nextgest:enviar-lembretes');

    Queue::assertNotPushed(EnviarLembreteWhatsApp::class);
});

it('a tela bloqueia/libera cada consentimento de forma independente', function () {
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@mkt.test']), 'web');
    $cli = clienteMkt(geral: false, marketing: false);

    // Sair do marketing → só o flag de marketing muda; o geral fica intacto.
    Livewire::test(OptOut::class)->call('bloquear', $cli->id, 'marketing');
    $cli->refresh();
    expect($cli->whatsapp_marketing_optout)->toBeTrue()
        ->and($cli->whatsapp_optout)->toBeFalse();

    // Bloquear tudo → só o geral muda.
    Livewire::test(OptOut::class)->call('bloquear', $cli->id, 'geral');
    $cli->refresh();
    expect($cli->whatsapp_optout)->toBeTrue();

    // Liberar o marketing não reativa o geral.
    Livewire::test(OptOut::class)->call('liberar', $cli->id, 'marketing');
    $cli->refresh();
    expect($cli->whatsapp_marketing_optout)->toBeFalse()
        ->and($cli->whatsapp_optout)->toBeTrue();
});

it('tipo inválido não altera nada (defensivo)', function () {
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono2@mkt.test']), 'web');
    $cli = clienteMkt(false, false);

    Livewire::test(OptOut::class)->call('bloquear', $cli->id, 'xpto');
    $cli->refresh();
    expect($cli->whatsapp_optout)->toBeFalse()
        ->and($cli->whatsapp_marketing_optout)->toBeFalse();
});
