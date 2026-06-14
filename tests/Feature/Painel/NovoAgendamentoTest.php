<?php

declare(strict_types=1);

use App\Livewire\Painel\Agenda\NovoAgendamento;
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
});

afterEach(fn () => Carbon::setTestNow());

it('cria agendamento manual com cliente novo (origem=equipe)', function () {
    $dono = usuarioComPapel('Dono');
    Livewire::actingAs($dono, 'web');

    Livewire::test(NovoAgendamento::class)
        ->call('abrir')
        ->set('novoNome', 'Cliente Balcão')
        ->set('novoTelefone', '11999990000')
        ->call('criarCliente')
        ->call('toggleServico', $this->corte->id)
        ->call('irParaProfissional')
        ->call('selecionarProfissional', (string) $this->prof->id)
        ->set('data', $this->dia)
        ->call('selecionarSlot', '10:00', $this->prof->id)
        ->call('confirmar')
        ->assertDispatched('agenda-atualizada');

    $cliente = Cliente::firstWhere('nome', 'Cliente Balcão');
    $ag = Agendamento::where('cliente_id', $cliente->id)->first();

    expect($ag)->not->toBeNull()
        ->and($ag->origem)->toBe('equipe')
        ->and($ag->criado_por_user_id)->toBe($dono->id)
        ->and($ag->itens->first()->duracao_minutos)->toBe(60);
});

it('não duplica quando o horário foi tomado durante o wizard', function () {
    Livewire::actingAs(usuarioComPapel('Dono'), 'web');
    $cli = Cliente::create(['nome' => 'Fulano', 'telefone' => '11', 'email' => 'f@l.test']);
    $inicio = Carbon::parse($this->dia.' 10:00');

    // Outro agendamento ocupa o slot antes da confirmação.
    $outro = Cliente::create(['nome' => 'Outro', 'telefone' => '22', 'email' => 'o@l.test']);
    app(Agendador::class)->agendarPelaEquipe($outro->id, $this->unidade->id, [$this->corte->id], $this->prof->id, $inicio, 1);

    Livewire::test(NovoAgendamento::class)
        ->call('abrir')
        ->call('selecionarCliente', $cli->id)
        ->call('toggleServico', $this->corte->id)
        ->call('irParaProfissional')
        ->call('selecionarProfissional', (string) $this->prof->id)
        ->set('data', $this->dia)
        ->call('selecionarSlot', '10:00', $this->prof->id)
        ->call('confirmar')
        ->assertNotDispatched('agenda-atualizada');

    expect(Agendamento::where('cliente_id', $cli->id)->count())->toBe(0);
    expect(Agendamento::where('profissional_id', $this->prof->id)->where('data_hora_inicio', $inicio)->ocupantes()->count())->toBe(1);
});

it('bloqueia quem não tem gerir_agenda de abrir o agendamento manual', function () {
    // Profissional não tem gerir_agenda.
    Livewire::actingAs($this->prof, 'web');

    Livewire::test(NovoAgendamento::class)
        ->call('abrir')
        ->assertForbidden();
});
