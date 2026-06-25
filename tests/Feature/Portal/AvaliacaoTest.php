<?php

declare(strict_types=1);

use App\Livewire\Portal\Home;
use App\Models\Agendamento;
use App\Models\Avaliacao;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

/**
 * Avaliações (D51, coleta): popup uma vez, avaliar pelo histórico, 1-por-atendimento,
 * escopo do cliente, só concluído. Guard `cliente` (portal).
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojaaval');
    tenancy()->initialize($this->tenant);

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->prof = usuarioComPapel('Profissional', ['email' => 'prof@aval.test', 'e_profissional' => true]);
    $this->servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);
    $this->cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '11', 'email' => 'maria@aval.test']);
});

function atendimento(Cliente $cliente, $self, string $status = 'concluido', bool $popupVisto = false, ?Carbon $quando = null): Agendamento
{
    $quando ??= Carbon::now()->subDays(1)->setTime(10, 0);
    $ag = Agendamento::create([
        'unidade_id' => $self->unidade->id,
        'cliente_id' => $cliente->id,
        'profissional_id' => $self->prof->id,
        'data_hora_inicio' => $quando,
        'data_hora_fim' => $quando->copy()->addMinutes(30),
        'status' => $status,
        'origem' => 'cliente',
        'valor_total' => 40,
        'avaliacao_popup_exibido_em' => $popupVisto ? Carbon::now() : null,
    ]);
    $ag->itens()->create(['servico_id' => $self->servico->id, 'preco' => 40, 'duracao_minutos' => 30]);

    return $ag;
}

it('o popup abre ao carregar e cria a avaliação (uma vez)', function () {
    $ag = atendimento($this->cliente, $this);
    $this->actingAs($this->cliente, 'cliente');

    Livewire::test(Home::class)
        ->assertSet('mostrarAvaliacao', true)
        ->assertSet('avaliandoId', $ag->id)
        ->set('nota', 5)
        ->set('comentario', 'Excelente!')
        ->call('salvarAvaliacao')
        ->assertHasNoErrors();

    $aval = Avaliacao::where('agendamento_id', $ag->id)->first();
    expect($aval)->not->toBeNull();
    expect($aval->nota)->toBe(5);
    expect($aval->comentario)->toBe('Excelente!');
    expect($aval->cliente_id)->toBe($this->cliente->id);
    expect($aval->profissional_id)->toBe($this->prof->id);
    expect($aval->unidade_id)->toBe($this->unidade->id);

    // Popup foi marcado como exibido no load → não reaparece.
    expect($ag->fresh()->avaliacao_popup_exibido_em)->not->toBeNull();
    Livewire::test(Home::class)->assertSet('mostrarAvaliacao', false);
});

it('exige a nota ao salvar (comentário é opcional)', function () {
    atendimento($this->cliente, $this);
    $this->actingAs($this->cliente, 'cliente');

    Livewire::test(Home::class)
        ->assertSet('mostrarAvaliacao', true)
        ->call('salvarAvaliacao')
        ->assertHasErrors('nota');

    expect(Avaliacao::count())->toBe(0);
});

it('ignorar fecha sem criar avaliação, mas segue avaliável pelo histórico', function () {
    $ag = atendimento($this->cliente, $this);
    $this->actingAs($this->cliente, 'cliente');

    $c = Livewire::test(Home::class)
        ->assertSet('mostrarAvaliacao', true)
        ->call('ignorarAvaliacao')
        ->assertSet('mostrarAvaliacao', false);

    expect(Avaliacao::count())->toBe(0);
    // Popup já exibido, mas o atendimento continua avaliável pelo histórico.
    expect($ag->fresh()->avaliacao_popup_exibido_em)->not->toBeNull();
    $c->call('abrirAvaliacao', $ag->id)
        ->assertSet('mostrarAvaliacao', true)
        ->set('nota', 4)
        ->call('salvarAvaliacao')
        ->assertHasNoErrors();

    expect(Avaliacao::where('agendamento_id', $ag->id)->value('nota'))->toBe(4);
});

it('avalia pelo histórico quando o popup já foi exibido', function () {
    $ag = atendimento($this->cliente, $this, popupVisto: true);
    $this->actingAs($this->cliente, 'cliente');

    Livewire::test(Home::class)
        ->assertSet('mostrarAvaliacao', false) // popup não reabre
        ->call('abrirAvaliacao', $ag->id)
        ->set('nota', 3)
        ->call('salvarAvaliacao')
        ->assertHasNoErrors();

    expect(Avaliacao::where('agendamento_id', $ag->id)->value('nota'))->toBe(3);
});

it('garante 1 avaliação por atendimento (unique)', function () {
    $ag = atendimento($this->cliente, $this);
    Avaliacao::create([
        'agendamento_id' => $ag->id, 'cliente_id' => $this->cliente->id,
        'profissional_id' => $this->prof->id, 'unidade_id' => $this->unidade->id, 'nota' => 5,
    ]);

    expect(fn () => Avaliacao::create([
        'agendamento_id' => $ag->id, 'cliente_id' => $this->cliente->id,
        'profissional_id' => $this->prof->id, 'unidade_id' => $this->unidade->id, 'nota' => 3,
    ]))->toThrow(QueryException::class);
});

it('não deixa avaliar atendimento de OUTRO cliente', function () {
    $outro = Cliente::create(['nome' => 'João', 'telefone' => '22', 'email' => 'joao@aval.test']);
    $ag = atendimento($outro, $this, popupVisto: true);

    $this->actingAs($this->cliente, 'cliente');

    expect(fn () => Livewire::test(Home::class)->call('abrirAvaliacao', $ag->id))
        ->toThrow(ModelNotFoundException::class);

    expect(Avaliacao::count())->toBe(0);
});

it('não deixa avaliar atendimento NÃO concluído', function () {
    $ag = atendimento($this->cliente, $this, status: 'confirmado');
    $this->actingAs($this->cliente, 'cliente');

    Livewire::test(Home::class)->assertSet('mostrarAvaliacao', false); // não elegível ao popup

    expect(fn () => Livewire::test(Home::class)->call('abrirAvaliacao', $ag->id))
        ->toThrow(ModelNotFoundException::class);
});

// --- D51 refinamento: popup SÓ do atendimento mais recente ---

it('popup aparece só para o MAIS RECENTE (não para os antigos)', function () {
    $antigo = atendimento($this->cliente, $this, quando: Carbon::now()->subDays(10)->setTime(9, 0));
    $recente = atendimento($this->cliente, $this, quando: Carbon::now()->subDays(1)->setTime(9, 0));
    $this->actingAs($this->cliente, 'cliente');

    Livewire::test(Home::class)
        ->assertSet('mostrarAvaliacao', true)
        ->assertSet('avaliandoId', $recente->id); // o recente, nunca o antigo
});

it('TESTE-CHAVE (sem bombardeio): recente IGNORADO → nenhum popup, mesmo com antigos pendentes', function () {
    // Antigo pendente (sem popup, não avaliado) — NÃO pode ser promovido.
    atendimento($this->cliente, $this, quando: Carbon::now()->subDays(10)->setTime(9, 0));
    // Mais recente já com popup exibido (ignorado).
    atendimento($this->cliente, $this, popupVisto: true, quando: Carbon::now()->subDays(1)->setTime(9, 0));
    $this->actingAs($this->cliente, 'cliente');

    Livewire::test(Home::class)->assertSet('mostrarAvaliacao', false);
});

it('sem bombardeio: recente já AVALIADO → nenhum popup, mesmo com antigos pendentes', function () {
    atendimento($this->cliente, $this, quando: Carbon::now()->subDays(10)->setTime(9, 0)); // antigo pendente
    $recente = atendimento($this->cliente, $this, quando: Carbon::now()->subDays(1)->setTime(9, 0));
    Avaliacao::create([
        'agendamento_id' => $recente->id, 'cliente_id' => $this->cliente->id,
        'profissional_id' => $this->prof->id, 'unidade_id' => $this->unidade->id, 'nota' => 5,
    ]);
    $this->actingAs($this->cliente, 'cliente');

    Livewire::test(Home::class)->assertSet('mostrarAvaliacao', false);
});

it('surgindo um atendimento MAIS NOVO, ele vira o do popup (uma vez)', function () {
    // Cenário: recente anterior já ignorado; antigo pendente; nada de popup...
    atendimento($this->cliente, $this, quando: Carbon::now()->subDays(10)->setTime(9, 0));
    atendimento($this->cliente, $this, popupVisto: true, quando: Carbon::now()->subDays(2)->setTime(9, 0));
    $this->actingAs($this->cliente, 'cliente');
    Livewire::test(Home::class)->assertSet('mostrarAvaliacao', false);

    // ...até surgir um concluído MAIS NOVO → ele ganha o popup.
    $novo = atendimento($this->cliente, $this, quando: Carbon::now()->subHours(1));
    Livewire::test(Home::class)
        ->assertSet('mostrarAvaliacao', true)
        ->assertSet('avaliandoId', $novo->id);

    // Marcado como exibido → não reaparece.
    expect($novo->fresh()->avaliacao_popup_exibido_em)->not->toBeNull();
    Livewire::test(Home::class)->assertSet('mostrarAvaliacao', false);
});

it('histórico continua avaliando ANTIGOS mesmo sem popup', function () {
    $antigo = atendimento($this->cliente, $this, quando: Carbon::now()->subDays(10)->setTime(9, 0));
    atendimento($this->cliente, $this, popupVisto: true, quando: Carbon::now()->subDays(1)->setTime(9, 0)); // recente tratado
    $this->actingAs($this->cliente, 'cliente');

    Livewire::test(Home::class)
        ->assertSet('mostrarAvaliacao', false) // sem popup
        ->call('abrirAvaliacao', $antigo->id)  // mas o antigo é avaliável pelo histórico
        ->set('nota', 4)
        ->call('salvarAvaliacao')
        ->assertHasNoErrors();

    expect(Avaliacao::where('agendamento_id', $antigo->id)->value('nota'))->toBe(4);
});
