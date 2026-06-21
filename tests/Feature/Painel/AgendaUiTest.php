<?php

declare(strict_types=1);

use App\Livewire\Painel\Agenda\Index;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use Carbon\Carbon;
use Livewire\Livewire;

/**
 * Elevação de UI da agenda (Polimento 1). Estados, modal de detalhe e cancelamento
 * por modal — sem afrouxar as regras (Motor/Agendador), que seguem testadas à parte.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojaag');
    tenancy()->initialize($this->tenant);
    Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00'));

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45, 'ativo' => true]);
    $this->prof = profissionalAgenda($this->unidade, [$this->corte], [[2, '09:00', '18:00']], ['name' => 'Jorge']);
    $this->cliente = Cliente::create(['nome' => 'Maria Souza', 'telefone' => '1', 'email' => 'm@ag.test']);

    $this->ag = Agendamento::create([
        'unidade_id' => $this->unidade->id, 'cliente_id' => $this->cliente->id, 'profissional_id' => $this->prof->id,
        'data_hora_inicio' => Carbon::today()->setTime(10, 0), 'data_hora_fim' => Carbon::today()->setTime(10, 30),
        'status' => 'confirmado', 'origem' => 'equipe', 'valor_total' => 45,
    ]);
    $this->ag->itens()->create(['servico_id' => $this->corte->id, 'preco' => 45, 'duracao_minutos' => 30]);

    $this->dono = usuarioComPapel('Dono', ['email' => 'dono@ag.test']);
});

afterEach(fn () => Carbon::setTestNow());

it('clicar no agendamento abre o modal de detalhe', function () {
    Livewire::actingAs($this->dono, 'web');

    Livewire::test(Index::class)
        ->assertSet('mostrarDetalhe', false)
        ->call('abrirDetalhe', $this->ag->id)
        ->assertSet('mostrarDetalhe', true)
        ->assertSet('detalheId', $this->ag->id)
        ->assertSee('Maria Souza');
});

it('dia sem agendamentos mostra estado vazio (orientação)', function () {
    Livewire::actingAs($this->dono, 'web');

    Livewire::test(Index::class)
        ->set('data', '2026-07-16') // dia seguinte, sem agendamentos
        ->assertSee('Dia livre');
});

it('cancelar pelo modal cancela e libera o slot (regra do Agendador intacta)', function () {
    Livewire::actingAs($this->dono, 'web');

    Livewire::test(Index::class)
        ->call('abrirDetalhe', $this->ag->id)
        ->call('cancelarAgendamento');

    // A regra do Agendador foi aplicada: status cancelado (libera a agenda).
    expect($this->ag->fresh()->status)->toBe('cancelado');
});

it('a agenda renderiza no padrão elevado (ng-surface, sem confirm nativo)', function () {
    $html = $this->actingAs($this->dono, 'web')->get('/lojaag/painel/agenda')->assertOk()->content();

    expect($html)->toContain('ng-surface')          // superfícies/tokens da Etapa D
        ->and($html)->toContain('cancelar-agendamento') // confirmação por modal
        ->and($html)->not->toContain('wire:confirm');   // sem confirm nativo
});

it('a visão de semana usa rolagem com snap (responsivo)', function () {
    Livewire::actingAs($this->dono, 'web');

    expect(Livewire::test(Index::class)->set('visao', 'semana')->html())
        ->toContain('snap-x');
});
