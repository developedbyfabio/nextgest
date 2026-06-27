<?php

declare(strict_types=1);

use App\Livewire\Painel\AvisoProximoAtendimento;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use Carbon\Carbon;
use Livewire\Livewire;

/**
 * D69 — aviso "próximo atendimento chegando": toast (Flux) disparado por wire:init/poll
 * quando o PROFISSIONAL logado tem atendimento "a atender" em ≤ 15 min. Idempotente, só
 * profissional. (A checagem roda em `verificar()` — não no mount; ver o componente.)
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojaaviso');
    tenancy()->initialize($this->tenant);
    Carbon::setTestNow('2026-06-27 10:00:00');

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);
    $this->cliente = Cliente::create(['nome' => 'Maria Cliente', 'telefone' => '11', 'email' => 'm@aviso.test']);
    $this->prof = usuarioComPapel('Profissional', ['name' => 'Jorge Prof', 'email' => 'jorge@aviso.test', 'e_profissional' => true]);
});

afterEach(fn () => Carbon::setTestNow());

function agendarAviso(int $minutos, string $status = 'confirmado', $prof = null): Agendamento
{
    $self = test();
    $prof ??= $self->prof;
    $inicio = now()->copy()->addMinutes($minutos);
    $ag = Agendamento::create([
        'unidade_id' => $self->unidade->id, 'cliente_id' => $self->cliente->id,
        'profissional_id' => $prof->id, 'data_hora_inicio' => $inicio,
        'data_hora_fim' => $inicio->copy()->addMinutes(30), 'status' => $status,
        'origem' => 'equipe', 'valor_total' => 40,
    ]);
    $ag->itens()->create(['servico_id' => $self->servico->id, 'preco' => 40, 'duracao_minutos' => 30]);

    return $ag;
}

it('dispara o toast quando o atendimento começa em ≤ 15 min', function () {
    agendarAviso(10); // em 10 min (dentro da janela)
    $this->actingAs($this->prof, 'web');

    Livewire::test(AvisoProximoAtendimento::class)
        ->call('verificar')
        ->assertDispatched('toast-show');
});

it('não repete o toast (idempotente por sessão)', function () {
    $ag = agendarAviso(10);
    session()->put('aviso_proximo:'.$this->prof->id, [$ag->id]); // poll anterior já avisou
    $this->actingAs($this->prof, 'web');

    Livewire::test(AvisoProximoAtendimento::class)
        ->call('verificar')
        ->assertNotDispatched('toast-show');
});

it('marca o agendamento como avisado na sessão (não repete no próximo poll)', function () {
    $ag = agendarAviso(10);
    $this->actingAs($this->prof, 'web');

    Livewire::test(AvisoProximoAtendimento::class)
        ->call('verificar')
        ->assertDispatched('toast-show');

    expect(session()->get('aviso_proximo:'.$this->prof->id))->toContain($ag->id);
});

it('não dispara fora da janela (atendimento daqui a 20 min)', function () {
    agendarAviso(20);
    $this->actingAs($this->prof, 'web');

    Livewire::test(AvisoProximoAtendimento::class)->call('verificar')->assertNotDispatched('toast-show');
});

it('não dispara para atendimento cancelado/concluído/não-compareceu', function () {
    agendarAviso(10, 'cancelado');
    agendarAviso(10, 'concluido');
    agendarAviso(10, 'nao_compareceu');
    $this->actingAs($this->prof, 'web');

    Livewire::test(AvisoProximoAtendimento::class)->call('verificar')->assertNotDispatched('toast-show');
});

it('não dispara para usuário não-profissional (Dono)', function () {
    agendarAviso(10); // atendimento do Jorge
    $dono = usuarioComPapel('Dono', ['email' => 'dono@aviso.test']); // e_profissional = false
    $this->actingAs($dono, 'web');

    Livewire::test(AvisoProximoAtendimento::class)->call('verificar')->assertNotDispatched('toast-show');
});

it('só avisa o PROFISSIONAL daquele atendimento (não o de outro)', function () {
    $ana = usuarioComPapel('Profissional', ['name' => 'Ana', 'email' => 'ana@aviso.test', 'e_profissional' => true]);
    agendarAviso(10, 'confirmado', $ana); // atendimento da Ana, em 10 min

    // Jorge (também profissional) NÃO tem atendimento próprio na janela → sem toast.
    $this->actingAs($this->prof, 'web');
    Livewire::test(AvisoProximoAtendimento::class)->call('verificar')->assertNotDispatched('toast-show');

    // A Ana, sim.
    $this->actingAs($ana, 'web');
    Livewire::test(AvisoProximoAtendimento::class)->call('verificar')->assertDispatched('toast-show');
});
