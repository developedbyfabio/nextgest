<?php

declare(strict_types=1);

use App\Jobs\EnviarLembreteWhatsApp;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\WhatsappConfig;
use App\Services\WhatsApp\Aquecimento;
use App\Services\WhatsApp\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
| WhatsApp Modo Aquecimento (D82). Curva de volume p/ número novo, por cima das travas D79.
| Sem disparo real — modula volume (testes). Defaults conservadores em config('whatsapp.aquecimento').
*/
beforeEach(function () {
    $this->tenant = criarTenant('lojaaq');
    tenancy()->initialize($this->tenant);
    Carbon::setTestNow('2026-06-29 12:00:00');

    config([
        'whatsapp.base_url' => 'http://evo.test', 'whatsapp.api_key' => 'K',
        'whatsapp.lembretes.limite_por_dia' => 150,
        'whatsapp.lembretes.limite_por_minuto' => 20, // alto: deixa o aquecimento ser o gargalo
        'whatsapp.aquecimento.ativo' => true,
        'whatsapp.aquecimento.broadcast_a_partir_dia' => 11,
        'whatsapp.aquecimento.fases' => [
            ['ate_dia' => 2, 'limite_dia' => 10],
            ['ate_dia' => 6, 'limite_dia' => 20],
            ['ate_dia' => 13, 'limite_dia' => 40],
            ['ate_dia' => 21, 'limite_dia' => 80],
        ],
    ]);
    $this->tenant->recursos = ['whatsapp'];
    $this->tenant->save();
});

afterEach(fn () => Carbon::setTestNow());

/** WhatsappConfig conectado há $diasAtras dias (dia da curva = $diasAtras + 1). */
function cfgConectadoHa(int $diasAtras): WhatsappConfig
{
    return WhatsappConfig::create([
        'instancia' => 'ng_lojaaq', 'status_conexao' => 'open',
        'conectado_em' => now()->copy()->subDays($diasAtras),
        'automacoes' => ['lembrete_servico' => ['ativo' => true, 'template' => 'Oi {cliente}']],
    ]);
}

it('o teto efetivo sobe ao longo da curva (e termina no normal)', function () {
    $svc = app(Aquecimento::class);

    expect($svc->tetoEfetivoDia(cfgConectadoHa(0)))->toBe(10)   // dia 1
        ->and($svc->tetoEfetivoDia(tap(WhatsappConfig::first())->update(['conectado_em' => now()->subDays(4)])))->toBe(20)  // dia 5
        ->and($svc->tetoEfetivoDia(tap(WhatsappConfig::first())->update(['conectado_em' => now()->subDays(9)])))->toBe(40)  // dia 10
        ->and($svc->tetoEfetivoDia(tap(WhatsappConfig::first())->update(['conectado_em' => now()->subDays(19)])))->toBe(80) // dia 20
        ->and($svc->tetoEfetivoDia(tap(WhatsappConfig::first())->update(['conectado_em' => now()->subDays(30)])))->toBe(150); // curva concluída → normal
});

it('teto efetivo é o MENOR entre normal e curva', function () {
    config(['whatsapp.lembretes.limite_por_dia' => 5]); // normal menor que a curva
    expect(app(Aquecimento::class)->tetoEfetivoDia(cfgConectadoHa(19)))->toBe(5); // min(5, 80)
});

it('broadcast fica bloqueado até a fase madura (dia >= broadcast_a_partir_dia)', function () {
    $svc = app(Aquecimento::class);
    expect($svc->broadcastLiberado(cfgConectadoHa(4)))->toBeFalse()  // dia 5 < 11
        ->and($svc->broadcastLiberado(cfgConectadoHa(11)))->toBeTrue() // dia 12 >= 11
        ->and($svc->broadcastLiberado(cfgConectadoHa(30)))->toBeTrue(); // curva concluída
});

it('aquecimento desligado → sem cap de curva (teto normal) e broadcast liberado', function () {
    $cfg = cfgConectadoHa(0);
    $cfg->update(['aquecimento' => ['ativo' => false, 'fases' => [['ate_dia' => 2, 'limite_dia' => 10]]]]);
    $svc = app(Aquecimento::class);
    expect($svc->tetoEfetivoDia($cfg->fresh()))->toBe(150)
        ->and($svc->broadcastLiberado($cfg->fresh()))->toBeTrue();
});

it('comando aplica o teto do aquecimento (número novo → menos que o per-minute)', function () {
    Queue::fake();
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);
    cfgConectadoHa(0); // dia 1 → teto 10 (per-minute=20)

    $uni = Unidade::create(['nome' => 'M', 'ativo' => true]);
    $serv = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);
    $prof = usuarioComPapel('Profissional', ['name' => 'J', 'email' => 'j@aq.test', 'e_profissional' => true]);
    for ($i = 0; $i < 14; $i++) {
        $cli = Cliente::create(['nome' => 'C'.$i, 'telefone' => '4199999'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'email' => "c$i@aq.test"]);
        $ini = now()->copy()->addMinutes(30);
        $ag = Agendamento::create(['unidade_id' => $uni->id, 'cliente_id' => $cli->id, 'profissional_id' => $prof->id, 'data_hora_inicio' => $ini, 'data_hora_fim' => $ini->copy()->addMinutes(30), 'status' => 'confirmado', 'origem' => 'equipe', 'valor_total' => 40]);
        $ag->itens()->create(['servico_id' => $serv->id, 'preco' => 40, 'duracao_minutos' => 30]);
    }

    $this->artisan('nextgest:enviar-lembretes');

    Queue::assertPushed(EnviarLembreteWhatsApp::class, 10); // teto do aquecimento (dia 1), não 14 nem 20
});

it('status() reinicia a curva quando o número TROCA e mantém quando é o mesmo', function () {
    WhatsappConfig::create(['instancia' => 'ng_lojaaq', 'status_conexao' => 'close']);
    $svc = app(WhatsAppService::class);

    // Fake por closure: o owner muda por referência (Http::fake acumularia stubs).
    $owner = 'AAA@s.whatsapp.net';
    Http::fake(function ($request) use (&$owner) {
        if (str_contains($request->url(), 'connectionState')) {
            return Http::response(['instance' => ['state' => 'open']], 200);
        }
        if (str_contains($request->url(), 'fetchInstances')) {
            return Http::response([['name' => 'ng_lojaaq', 'ownerJid' => $owner]], 200);
        }

        return Http::response([], 200);
    });

    // 1ª conexão (owner A) em T1.
    Carbon::setTestNow('2026-06-29 12:00:00');
    $svc->status();
    $t1 = WhatsappConfig::first()->conectado_em;
    expect(WhatsappConfig::first()->numero_conectado)->toBe('AAA@s.whatsapp.net');

    // Caiu e voltou com OUTRO número (B) em T2 → reinicia a curva.
    WhatsappConfig::first()->update(['status_conexao' => 'close']);
    $owner = 'BBB@s.whatsapp.net';
    Carbon::setTestNow('2026-07-05 09:00:00');
    $svc->status();
    expect(WhatsappConfig::first()->numero_conectado)->toBe('BBB@s.whatsapp.net')
        ->and(WhatsappConfig::first()->conectado_em->gt($t1))->toBeTrue(); // reiniciou

    // Mesmo número (B) reconectado → mantém conectado_em.
    $t2 = WhatsappConfig::first()->conectado_em;
    WhatsappConfig::first()->update(['status_conexao' => 'close']);
    Carbon::setTestNow('2026-07-08 09:00:00');
    $svc->status();
    expect(WhatsappConfig::first()->conectado_em->eq($t2))->toBeTrue(); // não reiniciou
});
