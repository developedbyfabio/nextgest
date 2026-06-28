<?php

declare(strict_types=1);

use App\Jobs\EnviarLembreteWhatsApp;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\LembreteServico;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\WhatsappConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
| WhatsApp Fatia 4 (D79) — lembrete de serviço. Evolution mockada. Cobre: janela/fuso,
| idempotência (1 por agendamento), filtros (automação/conexão/opt-out/status), anti-ban
| (teto por minuto), e o job (render + envio + marca enviado/falhou). Real fica com o Fabio.
*/
beforeEach(function () {
    $this->tenant = criarTenant('lojalem');
    tenancy()->initialize($this->tenant);
    Carbon::setTestNow('2026-06-29 10:00:00');

    config([
        'whatsapp.base_url' => 'http://evo.test',
        'whatsapp.api_key' => 'GLOBALKEY',
        'whatsapp.lembretes.limite_por_minuto' => 4,
        'whatsapp.lembretes.limite_por_dia' => 150,
        'whatsapp.lembretes.intervalo_segundos' => 15,
    ]);
    $this->tenant->recursos = ['whatsapp'];
    $this->tenant->save();

    // Conectado + automação lembrete ligada, janela de 60 min.
    WhatsappConfig::create([
        'instancia' => 'ng_lojalem',
        'status_conexao' => 'open',
        'automacoes' => ['lembrete_servico' => [
            'ativo' => true,
            'template' => 'Oi {cliente}, {data} {hora} {servico}',
            'antecedencia_min' => 60,
        ]],
    ]);

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);
    $this->prof = usuarioComPapel('Profissional', ['name' => 'Jorge', 'email' => 'j@lem.test', 'e_profissional' => true]);
});

afterEach(fn () => Carbon::setTestNow());

function clienteLem(string $nome = 'Maria', bool $optout = false): Cliente
{
    return Cliente::create(['nome' => $nome, 'telefone' => '41999990000', 'email' => uniqid().'@l.test', 'whatsapp_optout' => $optout]);
}

function agLem(int $minutos, string $status = 'confirmado', ?Cliente $cliente = null): Agendamento
{
    $self = test();
    $cliente ??= clienteLem();
    $ini = now()->copy()->addMinutes($minutos);
    $ag = Agendamento::create([
        'unidade_id' => $self->unidade->id, 'cliente_id' => $cliente->id, 'profissional_id' => $self->prof->id,
        'data_hora_inicio' => $ini, 'data_hora_fim' => $ini->copy()->addMinutes(30),
        'status' => $status, 'origem' => 'equipe', 'valor_total' => 40,
    ]);
    $ag->itens()->create(['servico_id' => $self->servico->id, 'preco' => 40, 'duracao_minutos' => 30]);

    return $ag;
}

function fakeConectado(): void
{
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);
}

it('enfileira o agendamento que está na janela (1 job, 1 registro)', function () {
    Queue::fake();
    fakeConectado();
    agLem(30); // dentro de 60 min

    $this->artisan('nextgest:enviar-lembretes')->assertExitCode(0);

    Queue::assertPushed(EnviarLembreteWhatsApp::class, 1);
    expect(LembreteServico::count())->toBe(1);
});

it('é idempotente: rodar 2x não duplica (1 push, 1 registro)', function () {
    Queue::fake();
    fakeConectado();
    agLem(30);

    $this->artisan('nextgest:enviar-lembretes');
    $this->artisan('nextgest:enviar-lembretes');

    Queue::assertPushed(EnviarLembreteWhatsApp::class, 1);
    expect(LembreteServico::count())->toBe(1);
});

it('não enfileira fora da janela (antecedência 60, agendamento em 90 min)', function () {
    Queue::fake();
    fakeConectado();
    agLem(90);

    $this->artisan('nextgest:enviar-lembretes');
    Queue::assertNotPushed(EnviarLembreteWhatsApp::class);
});

it('não enfileira com a automação desligada', function () {
    WhatsappConfig::first()->update(['automacoes' => ['lembrete_servico' => ['ativo' => false, 'antecedencia_min' => 60]]]);
    Queue::fake();
    fakeConectado();
    agLem(30);

    $this->artisan('nextgest:enviar-lembretes');
    Queue::assertNotPushed(EnviarLembreteWhatsApp::class);
});

it('não enfileira com o WhatsApp desconectado (e não acumula)', function () {
    Queue::fake();
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'close']], 200)]);
    agLem(30);

    $this->artisan('nextgest:enviar-lembretes');
    Queue::assertNotPushed(EnviarLembreteWhatsApp::class);
    expect(LembreteServico::count())->toBe(0); // nada acumulado
});

it('não enfileira para cliente opt-out nem para status encerrado', function () {
    Queue::fake();
    fakeConectado();
    agLem(30, 'confirmado', clienteLem('OptOut', optout: true));
    agLem(30, 'cancelado');
    agLem(30, 'concluido');

    $this->artisan('nextgest:enviar-lembretes');
    Queue::assertNotPushed(EnviarLembreteWhatsApp::class);
});

it('anti-ban: respeita o teto por minuto (6 elegíveis, limite 4 → 4 enfileirados)', function () {
    Queue::fake();
    fakeConectado();
    for ($i = 0; $i < 6; $i++) {
        agLem(30, 'confirmado', clienteLem('C'.$i));
    }

    $this->artisan('nextgest:enviar-lembretes');

    Queue::assertPushed(EnviarLembreteWhatsApp::class, 4); // limite_por_minuto
    expect(LembreteServico::count())->toBe(4);
});

it('o job renderiza o template com dados reais e marca enviado', function () {
    Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'X'], 'status' => 'PENDING'], 201)]);
    $ag = agLem(30);
    $rec = LembreteServico::create(['agendamento_id' => $ag->id, 'status' => 'enfileirado', 'enfileirado_em' => now()]);

    (new EnviarLembreteWhatsApp('lojalem', $ag->id))->handle();

    tenancy()->initialize($this->tenant);
    expect(LembreteServico::find($rec->id)->status)->toBe('enviado');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/message/sendText/ng_lojalem')
        && str_contains($r['text'], 'Oi Maria,') && str_contains($r['text'], 'Corte'));
});

it('o job não reenvia se já enviado (idempotência no job)', function () {
    Http::fake(['evo.test/*' => Http::response([], 201)]);
    $ag = agLem(30);
    LembreteServico::create(['agendamento_id' => $ag->id, 'status' => 'enviado', 'enviado_em' => now()]);

    (new EnviarLembreteWhatsApp('lojalem', $ag->id))->handle();

    Http::assertNothingSent();
});

it('o job marca falhou (sem enviar) quando o cliente é opt-out', function () {
    Http::fake(['evo.test/*' => Http::response([], 201)]);
    $ag = agLem(30, 'confirmado', clienteLem('Opt', optout: true));
    $rec = LembreteServico::create(['agendamento_id' => $ag->id, 'status' => 'enfileirado', 'enfileirado_em' => now()]);

    (new EnviarLembreteWhatsApp('lojalem', $ag->id))->handle();

    tenancy()->initialize($this->tenant);
    expect(LembreteServico::find($rec->id)->status)->toBe('falhou');
    Http::assertNothingSent();
});
