<?php

declare(strict_types=1);

use App\Livewire\Painel\Clientes\Index;
use App\Models\Cliente;
use App\Models\MensagemWhatsapp;
use App\Models\WhatsappConfig;
use App\Services\WhatsApp\Aquecimento;
use App\Services\WhatsApp\EnvioAvulso;
use App\Services\WhatsApp\WhatsAppException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
| Clientes Fatia 2 (D88) — editar cliente + WhatsApp avulso 1 a 1. Evolution mockada.
| Cobre: edição (validação/normalização), avulso feliz + histórico, opt-out → confirmação,
| anti-ban (teto dia/minuto), desconectado e falha de envio. Real fica com o Fabio.
*/
beforeEach(function () {
    $this->tenant = criarTenant('cliac');
    tenancy()->initialize($this->tenant);
    Carbon::setTestNow('2026-06-29 10:00:00');

    config([
        'whatsapp.base_url' => 'http://evo.test',
        'whatsapp.api_key' => 'GLOBALKEY',
        'whatsapp.lembretes.limite_por_minuto' => 4,
        'whatsapp.lembretes.limite_por_dia' => 150,
    ]);
    $this->tenant->recursos = ['whatsapp'];
    $this->tenant->save();

    // Conectado + marco do aquecimento preenchido (evita o fetchInstances no status()).
    WhatsappConfig::create([
        'instancia' => 'ng_cliac',
        'status_conexao' => 'open',
        'conectado_em' => now(),
        'numero_conectado' => '5541999990000',
    ]);

    $this->dono = usuarioComPapel('Dono', ['email' => 'dono@cliac.test']);
});

afterEach(fn () => Carbon::setTestNow());

function clienteAc(string $nome = 'Maria', bool $optout = false, string $tel = '41999990000'): Cliente
{
    return Cliente::create([
        'nome' => $nome, 'telefone' => $tel, 'email' => uniqid().'@c.test', 'whatsapp_optout' => $optout,
    ]);
}

function fakeOk(): void
{
    Http::fake([
        'evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
        'evo.test/message/sendText/*' => Http::response(['key' => ['id' => 'WAMID'], 'status' => 'PENDING'], 200),
    ]);
}

// ---- Editar cliente -------------------------------------------------------

it('edita nome/email/telefone, normaliza o telefone para dígitos e salva', function () {
    $cli = clienteAc('Nome Antigo', tel: '41999990000');
    $this->actingAs($this->dono, 'web');

    Livewire::test(Index::class)
        ->call('abrirEditar', $cli->id)
        ->assertSet('editNome', 'Nome Antigo')
        ->set('editNome', 'Nome Novo')
        ->set('editEmail', 'novo@exemplo.com')
        ->set('editTelefone', '(41) 98888-7766')
        ->call('salvarEditar')
        ->assertHasNoErrors();

    $cli->refresh();
    expect($cli->nome)->toBe('Nome Novo');
    expect($cli->email)->toBe('novo@exemplo.com');
    expect($cli->telefone)->toBe('41988887766'); // só dígitos (BR)
});

it('rejeita email e telefone inválidos na edição', function () {
    $cli = clienteAc();
    $this->actingAs($this->dono, 'web');

    Livewire::test(Index::class)
        ->call('abrirEditar', $cli->id)
        ->set('editEmail', 'nao-eh-email')
        ->set('editTelefone', '123')
        ->call('salvarEditar')
        ->assertHasErrors(['editEmail', 'editTelefone']);
});

it('Recepção (ver_clientes) também edita', function () {
    $cli = clienteAc('Cliente Rec');
    $recepcao = usuarioComPapel('Recepção', ['email' => 'rec@cliac.test']);
    $this->actingAs($recepcao, 'web');

    Livewire::test(Index::class)
        ->call('abrirEditar', $cli->id)
        ->set('editNome', 'Editado pela Recepção')
        ->call('salvarEditar')
        ->assertHasNoErrors();

    expect($cli->fresh()->nome)->toBe('Editado pela Recepção');
});

// ---- WhatsApp avulso: caminho feliz + histórico ---------------------------

it('envia avulso (conectado, sem opt-out): registra no histórico como enviado', function () {
    fakeOk();
    $cli = clienteAc('Joao');
    $this->actingAs($this->dono, 'web');

    Livewire::test(Index::class)
        ->call('abrirWhatsapp', $cli->id)
        ->set('msgTexto', 'Olá, tudo bem?')
        ->call('tentarEnviar')
        ->assertHasNoErrors();

    $msg = MensagemWhatsapp::query()->where('automacao', 'avulso')->first();
    expect($msg)->not->toBeNull();
    expect($msg->status)->toBe(MensagemWhatsapp::ENVIADO);
    expect($msg->cliente_id)->toBe($cli->id);
});

it('avulso consome o orçamento diário combinado (Aquecimento::consumoHoje)', function () {
    fakeOk();
    $cli = clienteAc();
    $this->actingAs($this->dono, 'web');

    $antes = app(Aquecimento::class)->consumoHoje();

    Livewire::test(Index::class)
        ->call('abrirWhatsapp', $cli->id)
        ->set('msgTexto', 'oi')
        ->call('tentarEnviar');

    expect(app(Aquecimento::class)->consumoHoje())->toBe($antes + 1);
});

// ---- WhatsApp avulso: opt-out exige confirmação ---------------------------

it('cliente em opt-out: tentarEnviar NÃO envia; só confirmarEnvioOptout envia', function () {
    fakeOk();
    $cli = clienteAc('Sumido', optout: true);
    $this->actingAs($this->dono, 'web');

    $comp = Livewire::test(Index::class)
        ->call('abrirWhatsapp', $cli->id)
        ->assertSet('waOptout', true)
        ->set('msgTexto', 'promoção')
        ->call('tentarEnviar');

    expect(MensagemWhatsapp::count())->toBe(0); // ainda não enviou (espera confirmação)

    $comp->call('confirmarEnvioOptout')->assertHasNoErrors();

    expect(MensagemWhatsapp::query()->where('status', MensagemWhatsapp::ENVIADO)->count())->toBe(1);
});

// ---- Anti-ban: teto/dia e teto/minuto (CRÍTICO) ---------------------------

it('teto/dia: avulso bloqueia quando o orçamento do dia acabou (não fura o anti-ban)', function () {
    fakeOk();
    config(['whatsapp.aquecimento.ativo' => false, 'whatsapp.lembretes.limite_por_dia' => 1]);
    $cli = clienteAc();

    // Já houve 1 avulso hoje → restante do dia = 0.
    MensagemWhatsapp::create(['automacao' => 'avulso', 'status' => 'enviado', 'telefone' => '41999990000', 'enviado_em' => now()]);

    expect(fn () => app(EnvioAvulso::class)->enviar($cli, 'oi'))
        ->toThrow(WhatsAppException::class, 'limite diário');

    // Não chegou a chamar o envio (nenhuma mensagem nova).
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/message/sendText/'));
    expect(MensagemWhatsapp::count())->toBe(1);
});

it('teto/minuto: avulso bloqueia rajada no último minuto', function () {
    fakeOk();
    config(['whatsapp.aquecimento.ativo' => false, 'whatsapp.lembretes.limite_por_minuto' => 2]);
    $cli = clienteAc();

    // 2 avulsos no último minuto → estourou o teto/minuto.
    MensagemWhatsapp::create(['automacao' => 'avulso', 'status' => 'enviado', 'telefone' => 'x', 'enviado_em' => now()]);
    MensagemWhatsapp::create(['automacao' => 'avulso', 'status' => 'enviado', 'telefone' => 'x', 'enviado_em' => now()]);

    expect(fn () => app(EnvioAvulso::class)->enviar($cli, 'oi'))
        ->toThrow(WhatsAppException::class, 'último minuto');
});

// ---- Desconectado / falha de envio ----------------------------------------

it('desconectado: não envia e avisa (sem registro de envio)', function () {
    Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'close']], 200)]);
    $cli = clienteAc();
    $this->actingAs($this->dono, 'web');

    Livewire::test(Index::class)
        ->call('abrirWhatsapp', $cli->id)
        ->set('msgTexto', 'oi')
        ->call('tentarEnviar');

    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/message/sendText/'));
    expect(MensagemWhatsapp::count())->toBe(0);
});

it('falha no envio: registra falhou e lança exceção amigável (sem 500)', function () {
    Http::fake([
        'evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
        'evo.test/message/sendText/*' => Http::response(['message' => 'erro'], 500),
    ]);
    $cli = clienteAc();

    expect(fn () => app(EnvioAvulso::class)->enviar($cli, 'oi'))->toThrow(WhatsAppException::class);

    $msg = MensagemWhatsapp::query()->where('automacao', 'avulso')->first();
    expect($msg?->status)->toBe(MensagemWhatsapp::FALHOU);
});

it('avulso só com o recurso whatsapp: sem o recurso, abrir dá 404', function () {
    $this->tenant->recursos = [];
    $this->tenant->save();
    $cli = clienteAc();
    $this->actingAs($this->dono, 'web');

    Livewire::test(Index::class)
        ->call('abrirWhatsapp', $cli->id)
        ->assertStatus(404);
});
