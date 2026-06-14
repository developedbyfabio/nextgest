<?php

declare(strict_types=1);

use App\Livewire\Portal\Agendar;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Agendamento\Agendador;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);

    Carbon::setTestNow(Carbon::parse('2026-07-06 08:00:00'));
    $this->dia = Carbon::now()->format('Y-m-d');
    $diaSemana = (int) Carbon::now()->dayOfWeek;

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 60, 'preco' => 50, 'ativo' => true]);
    $this->corte->unidades()->sync([$this->unidade->id]);
    $this->prof = profissionalAgenda($this->unidade, [$this->corte], [[$diaSemana, '09:00', '18:00']], ['name' => 'Ana']);
    $this->cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '1199', 'email' => 'maria@l.test']);
});

afterEach(fn () => Carbon::setTestNow());

it('exige login do cliente para agendar', function () {
    $this->get('/lojaum/agendar')->assertRedirect(route('cliente.login', ['tenant' => 'lojaum']));
});

it('agenda de ponta a ponta (unidade única, sem preferência)', function () {
    $this->actingAs($this->cliente, 'cliente');

    Livewire::test(Agendar::class)
        ->assertSet('passo', 2) // unidade única → pula filial
        ->call('toggleServico', $this->corte->id)
        ->call('irParaProfissional')
        ->assertSet('passo', 3)
        ->call('selecionarProfissional', 'sem')
        ->assertSet('passo', 4)
        ->set('data', $this->dia)
        ->call('selecionarSlot', '10:00', $this->prof->id)
        ->call('confirmar')
        ->assertRedirect(route('tenant.home', ['tenant' => 'lojaum']));

    $ag = Agendamento::where('cliente_id', $this->cliente->id)->first();
    expect($ag)->not->toBeNull()
        ->and($ag->status)->toBe('confirmado')
        ->and($ag->data_hora_inicio->format('H:i'))->toBe('10:00')
        ->and($ag->profissional_id)->toBe($this->prof->id);
});

it('não duplica quando o horário foi tomado durante o wizard', function () {
    $this->actingAs($this->cliente, 'cliente');
    $inicio = Carbon::parse($this->dia.' 10:00');

    // Outro cliente confirma o mesmo horário antes.
    $outro = Cliente::create(['nome' => 'João', 'telefone' => '1188', 'email' => 'joao@l.test']);
    app(Agendador::class)->confirmar($outro, $this->unidade->id, [$this->corte->id], $this->prof->id, $inicio);

    Livewire::test(Agendar::class)
        ->call('toggleServico', $this->corte->id)
        ->call('irParaProfissional')
        ->call('selecionarProfissional', 'sem')
        ->set('data', $this->dia)
        ->call('selecionarSlot', '10:00', $this->prof->id)
        ->call('confirmar')
        ->assertNoRedirect();

    // Apenas o agendamento do "outro" cliente existe nesse horário.
    expect(Agendamento::where('profissional_id', $this->prof->id)->where('data_hora_inicio', $inicio)->ocupantes()->count())->toBe(1);
    expect(Agendamento::where('cliente_id', $this->cliente->id)->count())->toBe(0);
});
