<?php

declare(strict_types=1);

use App\Jobs\EnviarAvaliacaoWhatsApp;
use App\Livewire\Painel\Avaliacoes\Index;
use App\Livewire\Portal\AvaliacaoPublica;
use App\Models\Agendamento;
use App\Models\Avaliacao;
use App\Models\Cliente;
use App\Models\PedidoAvaliacao;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\WhatsappConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/*
| WhatsApp Fatia 5 (D81) — avaliação pós-serviço por LINK. Evolution mockada. Cobre: link
| assinado (abre/recusa forjado/sem dado), criação reusada (D51), janela/idempotência/
| filtros/anti-ban (espelha D79) e anonimato preservado. NÃO recebe nada pelo WhatsApp.
*/
beforeEach(function () {
    $this->tenant = criarTenant('lojaava');
    tenancy()->initialize($this->tenant);
    Carbon::setTestNow('2026-06-29 12:00:00');

    config([
        'whatsapp.base_url' => 'http://evo.test', 'whatsapp.api_key' => 'K',
        'whatsapp.termo_versao' => '1',
        'whatsapp.lembretes.limite_por_minuto' => 4,
        'whatsapp.avaliacao.apos_min_padrao' => 60,
        'whatsapp.avaliacao.janela_buffer_min' => 30,
    ]);
    $this->tenant->recursos = ['whatsapp'];
    $this->tenant->save();

    // Conectado + automação avaliação ligada + termo aceito (D80).
    WhatsappConfig::create([
        'instancia' => 'ng_lojaava', 'status_conexao' => 'open',
        'termo_aceito_em' => now(), 'termo_aceito_por' => 'Dono', 'termo_versao' => '1',
        'automacoes' => ['avaliacao_pos_servico' => [
            'ativo' => true, 'template' => 'Oi {cliente}, avalie: {link}', 'apos_min' => 60,
        ]],
    ]);

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);
    $this->prof = usuarioComPapel('Profissional', ['name' => 'Jorge', 'email' => 'j@ava.test', 'e_profissional' => true]);
});

afterEach(fn () => Carbon::setTestNow());

function clienteAva(bool $optout = false): Cliente
{
    return Cliente::create(['nome' => 'Maria', 'telefone' => '41999990000', 'email' => uniqid().'@a.test', 'whatsapp_optout' => $optout]);
}

/** Agendamento que TERMINOU há $minAtras minutos (status default concluido). */
function agConcluido(int $minAtras, string $status = 'concluido', ?Cliente $cli = null): Agendamento
{
    $self = test();
    $cli ??= clienteAva();
    $fim = now()->copy()->subMinutes($minAtras);
    $ini = $fim->copy()->subMinutes(30);
    $ag = Agendamento::create([
        'unidade_id' => $self->unidade->id, 'cliente_id' => $cli->id, 'profissional_id' => $self->prof->id,
        'data_hora_inicio' => $ini, 'data_hora_fim' => $fim, 'status' => $status, 'origem' => 'equipe', 'valor_total' => 40,
    ]);
    $ag->itens()->create(['servico_id' => $self->servico->id, 'preco' => 40, 'duracao_minutos' => 30]);

    return $ag;
}

// ---- Link público assinado (anonimato) ----

it('o link ASSINADO abre a avaliação do atendimento (sem login)', function () {
    $ag = agConcluido(70);
    $url = URL::temporarySignedRoute('tenant.avaliar', now()->addDay(), ['tenant' => 'lojaava', 'agendamento' => $ag->id]);
    tenancy()->end();

    $this->get($url)->assertOk()->assertSee('Como foi seu atendimento');
});

it('rejeita link SEM assinatura e link com assinatura de OUTRO atendimento → 403', function () {
    $ag = agConcluido(70);
    $outro = agConcluido(70); // outro atendimento existente
    tenancy()->end();

    // Sem assinatura.
    $this->get("/lojaava/avaliar/{$ag->id}")->assertForbidden();

    // Assinatura válida para o $ag, mas trocando para o id do $outro → assinatura não bate
    // (prova que não dá p/ reaproveitar a assinatura de um para avaliar o de outro).
    $url = URL::temporarySignedRoute('tenant.avaliar', now()->addDay(), ['tenant' => 'lojaava', 'agendamento' => $ag->id]);
    $forjado = str_replace("/avaliar/{$ag->id}?", "/avaliar/{$outro->id}?", $url);
    $this->get($forjado)->assertForbidden();
});

it('a URL do link NÃO expõe dado pessoal (só id + assinatura)', function () {
    $ag = agConcluido(70);
    $url = URL::temporarySignedRoute('tenant.avaliar', now()->addDay(), ['tenant' => 'lojaava', 'agendamento' => $ag->id]);

    expect($url)->not->toContain('Maria')->not->toContain('41999990000');
});

it('a tela pública cria a avaliação (reusa D51) e anonimato no painel segue intacto', function () {
    $ag = agConcluido(70);

    Livewire::test(AvaliacaoPublica::class, ['agendamento' => $ag])
        ->set('nota', 5)->set('comentario', 'Ótimo')->call('salvar')->assertSet('enviado', true);

    $av = Avaliacao::where('agendamento_id', $ag->id)->first();
    expect($av->nota)->toBe(5)->and($av->cliente_id)->toBe($ag->cliente_id);

    // Anonimato: o escopo do PROFISSIONAL no painel não carrega o cliente (D51/D67).
    $this->actingAs($this->prof, 'web');
    $escopo = (fn () => $this->escopo())->call(new Index);
    expect($escopo->getQuery()->wheres)->not->toBeEmpty(); // forçado em profissional_id
});

it('a tela pública fica indisponível se já avaliado', function () {
    $ag = agConcluido(70);
    Avaliacao::create(['agendamento_id' => $ag->id, 'cliente_id' => $ag->cliente_id, 'profissional_id' => $ag->profissional_id, 'unidade_id' => $ag->unidade_id, 'nota' => 4]);

    Livewire::test(AvaliacaoPublica::class, ['agendamento' => $ag])->assertSet('indisponivel', true);
});

// ---- Comando / job (espelha D79) ----

it('enfileira o atendimento concluído na janela (1) e é idempotente', function () {
    Queue::fake();
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);
    agConcluido(70); // terminou há 70 min; janela = (now-60-30, now-60] = há 60..90 min

    $this->artisan('nextgest:enviar-avaliacoes');
    $this->artisan('nextgest:enviar-avaliacoes');

    Queue::assertPushed(EnviarAvaliacaoWhatsApp::class, 1);
    expect(PedidoAvaliacao::count())->toBe(1);
});

it('não enfileira fora da janela, nem já avaliado, nem opt-out, nem não-concluído', function () {
    Queue::fake();
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);
    agConcluido(20);                       // muito recente (antes da janela)
    agConcluido(70, 'confirmado');         // não concluído
    agConcluido(70, 'concluido', clienteAva(optout: true)); // opt-out
    $jaAval = agConcluido(70);
    Avaliacao::create(['agendamento_id' => $jaAval->id, 'cliente_id' => $jaAval->cliente_id, 'profissional_id' => $jaAval->profissional_id, 'unidade_id' => $jaAval->unidade_id, 'nota' => 5]);

    $this->artisan('nextgest:enviar-avaliacoes');
    Queue::assertNotPushed(EnviarAvaliacaoWhatsApp::class);
});

it('não enfileira sem o termo aceito (D80)', function () {
    WhatsappConfig::first()->update(['termo_aceito_em' => null]);
    Queue::fake();
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);
    agConcluido(70);

    $this->artisan('nextgest:enviar-avaliacoes');
    Queue::assertNotPushed(EnviarAvaliacaoWhatsApp::class);
});

it('anti-ban: respeita o teto por minuto (6 elegíveis, limite 4)', function () {
    Queue::fake();
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);
    for ($i = 0; $i < 6; $i++) {
        agConcluido(70, 'concluido', clienteAva());
    }

    $this->artisan('nextgest:enviar-avaliacoes');
    Queue::assertPushed(EnviarAvaliacaoWhatsApp::class, 4);
});

it('o job envia a mensagem com o link assinado e marca enviado', function () {
    Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'X'], 'status' => 'PENDING'], 201)]);
    $ag = agConcluido(70);
    $rec = PedidoAvaliacao::create(['agendamento_id' => $ag->id, 'status' => 'enfileirado', 'enfileirado_em' => now()]);

    (new EnviarAvaliacaoWhatsApp('lojaava', $ag->id))->handle();

    tenancy()->initialize($this->tenant);
    expect(PedidoAvaliacao::find($rec->id)->status)->toBe('enviado');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/message/sendText/ng_lojaava')
        && str_contains($r['text'], 'Oi Maria, avalie:')
        && str_contains($r['text'], '/avaliar/'.$ag->id)
        && str_contains($r['text'], 'signature='));
});
