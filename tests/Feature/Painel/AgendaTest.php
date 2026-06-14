<?php

declare(strict_types=1);

use App\Livewire\Painel\Agenda\Index;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Agendamento\Agendador;
use App\Services\Agendamento\MotorDisponibilidade;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);

    Carbon::setTestNow(Carbon::parse('2026-07-06 08:00:00'));
    $this->dia = Carbon::now()->copy();
    $diaSemana = (int) $this->dia->dayOfWeek;

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 60, 'preco' => 50, 'ativo' => true]);
    $this->corte->unidades()->sync([$this->unidade->id]);
    $this->profA = profissionalAgenda($this->unidade, [$this->corte], [[$diaSemana, '09:00', '18:00']], ['name' => 'Ana']);
    $this->profB = profissionalAgenda($this->unidade, [$this->corte], [[$diaSemana, '09:00', '18:00']], ['name' => 'Bruno']);

    $this->clienteA = Cliente::create(['nome' => 'ClienteDaAna', 'telefone' => '11', 'email' => 'a@l.test']);
    $this->clienteB = Cliente::create(['nome' => 'ClienteDoBruno', 'telefone' => '22', 'email' => 'b@l.test']);
    $this->agendador = app(Agendador::class);

    $this->agA = $this->agendador->agendarPelaEquipe($this->clienteA->id, $this->unidade->id, [$this->corte->id], $this->profA->id, $this->dia->copy()->setTime(10, 0), $this->profA->id);
    $this->agB = $this->agendador->agendarPelaEquipe($this->clienteB->id, $this->unidade->id, [$this->corte->id], $this->profB->id, $this->dia->copy()->setTime(10, 0), $this->profB->id);
});

afterEach(fn () => Carbon::setTestNow());

it('profissional vê só a própria agenda', function () {
    // Ana é profissional; o helper já deu papel Profissional a ela.
    Livewire::actingAs($this->profA, 'web');

    Livewire::test(Index::class)
        ->set('data', $this->dia->format('Y-m-d'))
        ->assertSee('ClienteDaAna')
        ->assertDontSee('ClienteDoBruno');
});

it('recepção vê a agenda de todos', function () {
    Livewire::actingAs(usuarioComPapel('Recepção'), 'web');

    Livewire::test(Index::class)
        ->set('data', $this->dia->format('Y-m-d'))
        ->assertSee('ClienteDaAna')
        ->assertSee('ClienteDoBruno');
});

it('aplica transição de status válida', function () {
    Livewire::actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->call('abrirDetalhe', $this->agA->id)
        ->call('mudarStatus', 'em_andamento');

    expect($this->agA->fresh()->status)->toBe('em_andamento');
});

it('rejeita transição de status inválida', function () {
    Livewire::actingAs(usuarioComPapel('Dono'), 'web');
    $this->agA->update(['status' => 'concluido']);

    Livewire::test(Index::class)
        ->call('abrirDetalhe', $this->agA->id)
        ->call('mudarStatus', 'pendente');

    expect($this->agA->fresh()->status)->toBe('concluido');
});

it('cancelar libera o horário', function () {
    Livewire::actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->call('abrirDetalhe', $this->agA->id)
        ->call('mudarStatus', 'cancelado');

    expect($this->agA->fresh()->status)->toBe('cancelado');

    $horas = app(MotorDisponibilidade::class)
        ->slots($this->unidade->id, [$this->corte->id], $this->profA->id, $this->dia)
        ->pluck('hora');
    expect($horas)->toContain('10:00');
});

it('remarca para um horário livre sem duplicar', function () {
    Livewire::actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->call('abrirDetalhe', $this->agA->id)
        ->call('iniciarRemarcacao')
        ->set('remarcarData', $this->dia->format('Y-m-d'))
        ->call('confirmarRemarcacao', '14:00');

    $this->agA->refresh();
    expect($this->agA->data_hora_inicio->format('H:i'))->toBe('14:00')
        ->and(Agendamento::where('cliente_id', $this->clienteA->id)->count())->toBe(1);
});
