<?php

declare(strict_types=1);

use App\Livewire\Portal\Home;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Agendamento\Agendador;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);

    Carbon::setTestNow(Carbon::parse('2026-07-06 08:00:00'));
    $diaSemana = (int) Carbon::now()->dayOfWeek;

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 60, 'preco' => 50, 'ativo' => true]);
    $this->corte->unidades()->sync([$this->unidade->id]);
    $this->prof = profissionalAgenda($this->unidade, [$this->corte], [[$diaSemana, '09:00', '18:00']]);
    $this->cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '1199', 'email' => 'maria@l.test']);
    $this->agendador = app(Agendador::class);
});

afterEach(fn () => Carbon::setTestNow());

it('cancela o próprio agendamento dentro da antecedência', function () {
    $ag = $this->agendador->confirmar($this->cliente, $this->unidade->id, [$this->corte->id], $this->prof->id, Carbon::now()->copy()->setTime(11, 0));

    $this->actingAs($this->cliente, 'cliente');
    Livewire::test(Home::class)->call('cancelar', $ag->id);

    expect($ag->fresh()->status)->toBe('cancelado');
});

it('não deixa cancelar agendamento de outro cliente', function () {
    $outro = Cliente::create(['nome' => 'João', 'telefone' => '1188', 'email' => 'joao@l.test']);
    $ag = $this->agendador->confirmar($outro, $this->unidade->id, [$this->corte->id], $this->prof->id, Carbon::now()->copy()->setTime(11, 0));

    $this->actingAs($this->cliente, 'cliente');

    expect(fn () => Livewire::test(Home::class)->call('cancelar', $ag->id))
        ->toThrow(ModelNotFoundException::class);

    expect($ag->fresh()->status)->toBe('confirmado');
});
