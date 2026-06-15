<?php

declare(strict_types=1);

use App\Livewire\Painel\Kanban\Index;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\KanbanCartao;
use App\Models\KanbanColuna;
use App\Models\KanbanQuadro;
use App\Models\Unidade;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojakb');
    tenancy()->initialize($this->tenant);
});

function quadroAtendimento(): KanbanQuadro
{
    return KanbanQuadro::where('tipo', 'atendimento')->first();
}

it('semeia os dois quadros padrão com colunas no tenant', function () {
    expect(KanbanQuadro::where('tipo', 'atendimento')->exists())->toBeTrue()
        ->and(KanbanQuadro::where('tipo', 'crm')->exists())->toBeTrue();

    $cols = quadroAtendimento()->colunas()->pluck('nome')->all();
    expect($cols)->toBe(['Aguardando', 'Em atendimento', 'Concluído', 'Pago']);
});

it('cria, renomeia e remove coluna (Dono)', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->call('novaColuna')->set('nomeColuna', 'Triagem')->call('salvarColuna')->assertHasNoErrors();

    $coluna = KanbanColuna::where('nome', 'Triagem')->first();
    expect($coluna)->not->toBeNull();

    Livewire::test(Index::class)->call('editarColuna', $coluna->id)->set('nomeColuna', 'Triagem rápida')->call('salvarColuna');
    expect($coluna->fresh()->nome)->toBe('Triagem rápida');

    Livewire::test(Index::class)->call('removerColuna', $coluna->id);
    expect(KanbanColuna::find($coluna->id))->toBeNull();
});

it('cria cartão com vínculos opcionais a cliente e agendamento', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    $unidade = Unidade::create(['nome' => 'U', 'ativo' => true]);
    $prof = usuarioComPapel('Profissional', ['email' => 'p@kb.test', 'e_profissional' => true]);
    $cliente = Cliente::create(['nome' => 'Cli', 'telefone' => '1']);
    $ag = Agendamento::create([
        'unidade_id' => $unidade->id, 'cliente_id' => $cliente->id, 'profissional_id' => $prof->id,
        'data_hora_inicio' => Carbon::today()->setTime(10, 0), 'data_hora_fim' => Carbon::today()->setTime(10, 30),
        'status' => 'confirmado', 'origem' => 'equipe',
    ]);

    $coluna = quadroAtendimento()->colunas()->first();

    Livewire::test(Index::class)
        ->call('novoCartao', $coluna->id)
        ->set('titulo', 'Atender Cli')
        ->set('clienteId', (string) $cliente->id)
        ->set('agendamentoId', (string) $ag->id)
        ->set('responsavelId', (string) $prof->id)
        ->call('salvarCartao')
        ->assertHasNoErrors();

    $cartao = KanbanCartao::where('titulo', 'Atender Cli')->first();
    expect($cartao->cliente_id)->toBe($cliente->id)
        ->and($cartao->agendamento_id)->toBe($ag->id)
        ->and($cartao->responsavel_user_id)->toBe($prof->id)
        ->and($cartao->coluna_id)->toBe($coluna->id);
});

it('move cartão entre colunas persistindo coluna e posição', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    [$c1, $c2] = quadroAtendimento()->colunas()->orderBy('ordem')->take(2)->get()->all();

    $a = KanbanCartao::create(['coluna_id' => $c1->id, 'titulo' => 'A', 'ordem' => 0]);
    $b = KanbanCartao::create(['coluna_id' => $c1->id, 'titulo' => 'B', 'ordem' => 1]);
    $x = KanbanCartao::create(['coluna_id' => $c2->id, 'titulo' => 'X', 'ordem' => 0]);

    // Move B para a coluna 2, na posição 0 (antes de X).
    Livewire::test(Index::class)->call('moverCartao', $b->id, $c2->id, 0);

    expect($b->fresh()->coluna_id)->toBe($c2->id)
        ->and($b->fresh()->ordem)->toBe(0)
        ->and($x->fresh()->ordem)->toBe(1)   // empurrado para baixo
        ->and($a->fresh()->ordem)->toBe(0);  // coluna de origem reindexada
});

it('move por menu (acessível) para o fim da coluna alvo', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    [$c1, $c2] = quadroAtendimento()->colunas()->orderBy('ordem')->take(2)->get()->all();
    $x = KanbanCartao::create(['coluna_id' => $c2->id, 'titulo' => 'X', 'ordem' => 0]);
    $a = KanbanCartao::create(['coluna_id' => $c1->id, 'titulo' => 'A', 'ordem' => 0]);

    Livewire::test(Index::class)->call('moverCartaoParaColuna', $a->id, $c2->id);

    expect($a->fresh()->coluna_id)->toBe($c2->id)
        ->and($a->fresh()->ordem)->toBe(1); // após X
});

it('gera cartões do dia a partir dos agendamentos de hoje, sem duplicar', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    $unidade = Unidade::create(['nome' => 'U', 'ativo' => true]);
    $prof = usuarioComPapel('Profissional', ['email' => 'p2@kb.test', 'e_profissional' => true]);
    $cliente = Cliente::create(['nome' => 'Hoje', 'telefone' => '9']);
    Agendamento::create([
        'unidade_id' => $unidade->id, 'cliente_id' => $cliente->id, 'profissional_id' => $prof->id,
        'data_hora_inicio' => Carbon::today()->setTime(9, 0), 'data_hora_fim' => Carbon::today()->setTime(9, 30),
        'status' => 'confirmado', 'origem' => 'equipe',
    ]);

    Livewire::test(Index::class)->call('gerarCartoesDoDia');
    Livewire::test(Index::class)->call('gerarCartoesDoDia'); // idempotente

    expect(KanbanCartao::whereNotNull('agendamento_id')->count())->toBe(1);
});

it('a Recepção acessa o Atendimento mas não o CRM', function () {
    $this->actingAs(usuarioComPapel('Recepção'), 'web');

    // Atendimento ok.
    Livewire::test(Index::class)->assertOk()->assertSet('tipo', 'atendimento');

    // CRM bloqueado (sem gerir_kanban).
    Livewire::test(Index::class)->call('trocarQuadro', 'crm')->assertStatus(403);
});

it('a Recepção não pode gerir colunas (sem gerir_kanban)', function () {
    $this->actingAs(usuarioComPapel('Recepção'), 'web');

    Livewire::test(Index::class)->call('novaColuna')->assertStatus(403);
});

it('o Profissional não acessa o kanban (403)', function () {
    $this->actingAs(usuarioComPapel('Profissional', ['email' => 'prof@kb.test', 'e_profissional' => true]), 'web')
        ->get('/lojakb/painel/kanban')
        ->assertForbidden();
});

it('o Gerente acessa os dois quadros', function () {
    $this->actingAs(usuarioComPapel('Gerente'), 'web');

    Livewire::test(Index::class)
        ->call('trocarQuadro', 'crm')
        ->assertSet('tipo', 'crm')
        ->assertHasNoErrors();
});
