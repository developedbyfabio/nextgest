<?php

declare(strict_types=1);

use App\Jobs\EnviarAvaliacaoWhatsApp;
use App\Jobs\EnviarLembreteWhatsApp;
use App\Livewire\Painel\Whatsapp\Historico;
use App\Livewire\Painel\Whatsapp\OptOut;
use App\Models\Agendamento;
use App\Models\Avaliacao;
use App\Models\Cliente;
use App\Models\LembreteServico;
use App\Models\MensagemWhatsapp;
use App\Models\PedidoAvaliacao;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\WhatsappConfig;
use App\Services\WhatsApp\JanelaEnvio;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
| Controle de mensagens de WhatsApp (D83). Log de envios (metadados + conteúdo c/ expurgo),
| janela de horário (global + override; adia/descarta no servidor, fuso APP_TIMEZONE) e
| gestão de opt-out. Evolution mockada. Anonimato D51 preservado (envio ≠ avaliação).
*/
beforeEach(function () {
    $this->tenant = criarTenant('lojacm');
    tenancy()->initialize($this->tenant);
    Carbon::setTestNow('2026-06-29 10:00:00'); // dentro da janela padrão (08–20)

    config([
        'whatsapp.base_url' => 'http://evo.test',
        'whatsapp.api_key' => 'GLOBALKEY',
        'whatsapp.lembretes.limite_por_minuto' => 4,
        'whatsapp.lembretes.limite_por_dia' => 150,
        'whatsapp.lembretes.intervalo_segundos' => 15,
        'whatsapp.aquecimento.ativo' => false, // aquecimento fora do caminho destes testes
        'whatsapp.janela' => ['ativa' => true, 'inicio' => '08:00', 'fim' => '20:00'],
        'whatsapp.historico.expurgo_dias' => 90,
    ]);
    $this->tenant->recursos = ['whatsapp'];
    $this->tenant->save();

    // Conectado há 1 dia (evita o fetchInstances do D82 no status()) + automações ligadas.
    WhatsappConfig::create([
        'instancia' => 'ng_lojacm',
        'status_conexao' => 'open',
        'conectado_em' => now()->copy()->subDays(1),
        'numero_conectado' => '55410000@s.whatsapp.net',
        'automacoes' => [
            'lembrete_servico' => ['ativo' => true, 'template' => 'Oi {cliente}', 'antecedencia_min' => 60],
            'avaliacao_pos_servico' => ['ativo' => true, 'template' => 'Avalie: {link}', 'apos_min' => 120],
        ],
    ]);

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);
    $this->prof = usuarioComPapel('Profissional', ['name' => 'Jorge', 'email' => 'j@cm.test', 'e_profissional' => true]);
});

afterEach(fn () => Carbon::setTestNow());

function clienteCm(string $nome = 'Maria', bool $optout = false): Cliente
{
    return Cliente::create(['nome' => $nome, 'telefone' => '41999990000', 'email' => uniqid().'@cm.test', 'whatsapp_optout' => $optout]);
}

function agCm(Carbon $inicio, string $status = 'confirmado', ?Cliente $cliente = null): Agendamento
{
    $self = test();
    $cliente ??= clienteCm();
    $ag = Agendamento::create([
        'unidade_id' => $self->unidade->id, 'cliente_id' => $cliente->id, 'profissional_id' => $self->prof->id,
        'data_hora_inicio' => $inicio, 'data_hora_fim' => $inicio->copy()->addMinutes(30),
        'status' => $status, 'origem' => 'equipe', 'valor_total' => 40,
    ]);
    $ag->itens()->create(['servico_id' => $self->servico->id, 'preco' => 40, 'duracao_minutos' => 30]);

    return $ag;
}

// ───────────────────────────── Log de envios ─────────────────────────────

it('grava no log (metadados + conteúdo) quando o lembrete é enviado', function () {
    Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'X']], 201)]);
    $cli = clienteCm('Ana');
    $ag = agCm(now()->copy()->addMinutes(30), 'confirmado', $cli);
    LembreteServico::create(['agendamento_id' => $ag->id, 'status' => 'enfileirado', 'enfileirado_em' => now()]);

    (new EnviarLembreteWhatsApp('lojacm', $ag->id))->handle();

    tenancy()->initialize($this->tenant);
    $log = MensagemWhatsapp::first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('enviado')
        ->and($log->automacao)->toBe('lembrete_servico')
        ->and($log->cliente_id)->toBe($cli->id)
        ->and($log->telefone)->toBe('41999990000')
        ->and($log->conteudo)->toContain('Oi Ana')
        ->and($log->enviado_em)->not->toBeNull();
});

it('grava status FALHOU no log quando o envio falha', function () {
    Http::fake(['evo.test/*' => Http::response([], 500)]);
    $ag = agCm(now()->copy()->addMinutes(30));
    LembreteServico::create(['agendamento_id' => $ag->id, 'status' => 'enfileirado', 'enfileirado_em' => now()]);

    (new EnviarLembreteWhatsApp('lojacm', $ag->id))->handle();

    tenancy()->initialize($this->tenant);
    expect(MensagemWhatsapp::where('status', 'falhou')->count())->toBe(1)
        ->and(LembreteServico::first()->status)->toBe('falhou');
});

it('mascara o link assinado da avaliação no conteúdo gravado', function () {
    Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'X']], 201)]);
    $ag = agCm(now()->copy()->subMinutes(130), 'concluido');
    PedidoAvaliacao::create(['agendamento_id' => $ag->id, 'status' => 'enfileirado', 'enfileirado_em' => now()]);

    (new EnviarAvaliacaoWhatsApp('lojacm', $ag->id))->handle();

    tenancy()->initialize($this->tenant);
    $log = MensagemWhatsapp::first();
    expect($log->status)->toBe('enviado')
        ->and($log->conteudo)->toContain('[link]')
        ->and($log->conteudo)->not->toContain('http'); // credencial não persiste
});

// ───────────────────────────── Expurgo ─────────────────────────────

it('o expurgo apaga o conteúdo antigo e mantém os metadados', function () {
    $antiga = MensagemWhatsapp::create(['automacao' => 'lembrete_servico', 'telefone' => '4199', 'status' => 'enviado', 'conteudo' => 'segredo antigo']);
    $antiga->created_at = now()->copy()->subDays(100);
    $antiga->save();
    MensagemWhatsapp::create(['automacao' => 'lembrete_servico', 'telefone' => '4188', 'status' => 'enviado', 'conteudo' => 'recente']);

    $this->artisan('nextgest:whatsapp-expurgar-conteudo')->assertExitCode(0);

    tenancy()->initialize($this->tenant);
    $antiga->refresh();
    expect($antiga->conteudo)->toBeNull()
        ->and($antiga->conteudo_expurgado_em)->not->toBeNull()
        ->and($antiga->telefone)->toBe('4199')   // metadado preservado
        ->and($antiga->status)->toBe('enviado')
        ->and(MensagemWhatsapp::where('conteudo', 'recente')->exists())->toBeTrue();
});

// ───────────────────────────── Janela de horário ─────────────────────────────

it('JanelaEnvio: aberta/fechada e próxima abertura respeitam o fuso', function () {
    $svc = app(JanelaEnvio::class);
    $j = $svc->paraAutomacao('lembrete_servico', WhatsappConfig::first());

    Carbon::setTestNow('2026-06-29 10:00:00');
    expect($svc->aberta($j))->toBeTrue();

    Carbon::setTestNow('2026-06-29 22:00:00');
    expect($svc->aberta($j))->toBeFalse()
        ->and($svc->proximaAbertura($j)->format('Y-m-d H:i'))->toBe('2026-06-30 08:00');

    Carbon::setTestNow('2026-06-29 06:00:00');
    expect($svc->proximaAbertura($j)->format('Y-m-d H:i'))->toBe('2026-06-29 08:00');
});

it('fora da janela com evento futuro: ADIA (não envia, não loga) e marca agendado_para', function () {
    Carbon::setTestNow('2026-06-29 22:00:00');
    Http::fake(['evo.test/*' => Http::response([], 201)]);
    $ag = agCm(Carbon::parse('2026-06-30 09:00:00')); // depois da próxima abertura (08:00)
    LembreteServico::create(['agendamento_id' => $ag->id, 'status' => 'enfileirado', 'enfileirado_em' => now()]);

    (new EnviarLembreteWhatsApp('lojacm', $ag->id))->handle();

    tenancy()->initialize($this->tenant);
    $rec = LembreteServico::first();
    expect($rec->status)->toBe('enfileirado')
        ->and($rec->agendado_para->format('Y-m-d H:i'))->toBe('2026-06-30 08:00')
        ->and(MensagemWhatsapp::count())->toBe(0);
    Http::assertNothingSent();
});

it('fora da janela com evento que já teria passado: DESCARTA (marcado no log)', function () {
    Carbon::setTestNow('2026-06-29 22:00:00');
    Http::fake(['evo.test/*' => Http::response([], 201)]);
    $ag = agCm(Carbon::parse('2026-06-30 07:30:00')); // antes da próxima abertura (08:00)
    LembreteServico::create(['agendamento_id' => $ag->id, 'status' => 'enfileirado', 'enfileirado_em' => now()]);

    (new EnviarLembreteWhatsApp('lojacm', $ag->id))->handle();

    tenancy()->initialize($this->tenant);
    expect(LembreteServico::first()->status)->toBe('falhou')
        ->and(MensagemWhatsapp::where('status', 'descartado')->where('automacao', 'lembrete_servico')->count())->toBe(1);
    Http::assertNothingSent();
});

it('avaliação fora da janela sempre ADIA (evento já ocorreu — nunca descarta)', function () {
    Carbon::setTestNow('2026-06-29 22:00:00');
    Http::fake(['evo.test/*' => Http::response([], 201)]);
    $ag = agCm(Carbon::parse('2026-06-29 19:00:00'), 'concluido');
    PedidoAvaliacao::create(['agendamento_id' => $ag->id, 'status' => 'enfileirado', 'enfileirado_em' => now()]);

    (new EnviarAvaliacaoWhatsApp('lojacm', $ag->id))->handle();

    tenancy()->initialize($this->tenant);
    $rec = PedidoAvaliacao::first();
    expect($rec->status)->toBe('enfileirado')
        ->and($rec->agendado_para->format('Y-m-d H:i'))->toBe('2026-06-30 08:00')
        ->and(MensagemWhatsapp::count())->toBe(0);
    Http::assertNothingSent();
});

it('override por automação usa a janela própria (e quem não tem usa a global)', function () {
    WhatsappConfig::first()->update(['automacoes' => [
        'lembrete_servico' => ['ativo' => true, 'janela' => ['ativa' => true, 'inicio' => '09:00', 'fim' => '10:00']],
        'avaliacao_pos_servico' => ['ativo' => true],
    ]]);
    $svc = app(JanelaEnvio::class);
    $cfg = WhatsappConfig::first();

    Carbon::setTestNow('2026-06-29 14:00:00'); // dentro da global (08–20), fora da própria do lembrete (09–10)
    expect($svc->aberta($svc->paraAutomacao('lembrete_servico', $cfg)))->toBeFalse()
        ->and($svc->aberta($svc->paraAutomacao('avaliacao_pos_servico', $cfg)))->toBeTrue();
});

it('o comando re-despacha os represados pela janela quando o horário vence', function () {
    Queue::fake();
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);
    $ag = agCm(now()->copy()->addMinutes(30));
    // Represado: enfileirado + agendado_para já vencido.
    LembreteServico::create(['agendamento_id' => $ag->id, 'status' => 'enfileirado', 'enfileirado_em' => now(), 'agendado_para' => now()->copy()->subMinute()]);

    $this->artisan('nextgest:enviar-lembretes')->assertExitCode(0);

    Queue::assertPushed(EnviarLembreteWhatsApp::class, 1);
    tenancy()->initialize($this->tenant);
    expect(LembreteServico::first()->agendado_para)->toBeNull(); // reclamado
});

// ───────────────────────────── Opt-out (tela) ─────────────────────────────

it('opt-out pela tela bloqueia e remover libera o envio', function () {
    Queue::fake();
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@cm.test']), 'web');
    $cli = clienteCm('Bruna');
    agCm(now()->copy()->addMinutes(30), 'confirmado', $cli);

    Livewire::test(OptOut::class)->call('marcar', $cli->id);
    expect(Cliente::find($cli->id)->whatsapp_optout)->toBeTrue();
    $this->artisan('nextgest:enviar-lembretes');
    Queue::assertNotPushed(EnviarLembreteWhatsApp::class);

    Livewire::test(OptOut::class)->call('desmarcar', $cli->id);
    expect(Cliente::find($cli->id)->whatsapp_optout)->toBeFalse();
    $this->artisan('nextgest:enviar-lembretes');
    Queue::assertPushed(EnviarLembreteWhatsApp::class, 1);
});

// ───────────────────────────── Gating + anonimato ─────────────────────────────

it('o histórico é gated: Profissional recebe 403, Dono acessa', function () {
    // Usuários criados no contexto do tenant (antes de encerrar a tenancy do beforeEach).
    $prof = usuarioComPapel('Profissional', ['email' => 'prof@cm.test', 'e_profissional' => true]);
    $dono = usuarioComPapel('Dono', ['email' => 'dono2@cm.test']);
    tenancy()->end();

    $this->actingAs($prof, 'web')->get('/lojacm/painel/whatsapp/historico')->assertForbidden();
    $this->actingAs($dono, 'web')->get('/lojacm/painel/whatsapp/historico')->assertOk();
});

it('o histórico não cruza com a avaliação (anonimato D51): não vaza a nota/comentário', function () {
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono3@cm.test']), 'web');
    $cli = clienteCm('Carla');
    $ag = agCm(now()->copy()->subDays(1), 'concluido', $cli);
    // Envio do pedido (log) + a avaliação real (D51) com um comentário sigiloso.
    MensagemWhatsapp::create(['automacao' => 'avaliacao_pos_servico', 'cliente_id' => $cli->id, 'agendamento_id' => $ag->id, 'telefone' => '4199', 'status' => 'enviado', 'conteudo' => 'Avalie: [link]']);
    Avaliacao::create(['agendamento_id' => $ag->id, 'cliente_id' => $cli->id, 'profissional_id' => $this->prof->id, 'unidade_id' => $this->unidade->id, 'nota' => 1, 'comentario' => 'SEGREDODANOTA']);

    Livewire::test(Historico::class)
        ->assertOk()
        ->assertSee('Carla')          // o ENVIO aparece
        ->assertDontSee('SEGREDODANOTA'); // o RESULTADO da avaliação NÃO
});
