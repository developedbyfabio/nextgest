<?php

declare(strict_types=1);

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Configuracao;
use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Agendamento\Agendador;
use App\Services\Agendamento\SlotIndisponivelException;
use Carbon\Carbon;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);

    Carbon::setTestNow(Carbon::parse('2026-07-06 08:00:00'));
    $this->dia = Carbon::now()->copy();
    $diaSemana = (int) $this->dia->dayOfWeek;

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 60, 'preco' => 50, 'ativo' => true]);
    $this->corte->unidades()->sync([$this->unidade->id]);
    $this->prof = profissionalAgenda($this->unidade, [$this->corte], [[$diaSemana, '09:00', '18:00']]);
    $this->cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '1199', 'email' => 'maria@l.test']);

    $this->agendador = app(Agendador::class);
    $this->inicio = $this->dia->copy()->setTime(10, 0);
});

afterEach(fn () => Carbon::setTestNow());

it('confirma agendamento com snapshots e total corretos', function () {
    $ag = $this->agendador->confirmar($this->cliente, $this->unidade->id, [$this->corte->id], $this->prof->id, $this->inicio);

    expect($ag->status)->toBe('confirmado')
        ->and($ag->origem)->toBe('cliente')
        ->and((float) $ag->valor_total)->toBe(50.0)
        ->and($ag->data_hora_fim->format('H:i'))->toBe('11:00')
        ->and($ag->itens)->toHaveCount(1);

    $item = $ag->itens->first();
    expect((float) $item->preco)->toBe(50.0)
        ->and($item->duracao_minutos)->toBe(60);
});

it('entra como pendente quando confirmacao_automatica é falsa', function () {
    Configuracao::where('chave', 'confirmacao_automatica')->update(['valor' => '0']);

    $ag = $this->agendador->confirmar($this->cliente, $this->unidade->id, [$this->corte->id], $this->prof->id, $this->inicio);

    expect($ag->status)->toBe('pendente');
});

it('impede duplicar o mesmo horário (concorrência)', function () {
    // Outro cliente "venceu" e gravou o mesmo slot entre a listagem e a confirmação.
    $outro = Cliente::create(['nome' => 'João', 'telefone' => '1188', 'email' => 'joao@l.test']);
    Agendamento::create([
        'unidade_id' => $this->unidade->id,
        'cliente_id' => $outro->id,
        'profissional_id' => $this->prof->id,
        'data_hora_inicio' => $this->inicio->copy(),
        'data_hora_fim' => $this->inicio->copy()->addMinutes(60),
        'status' => 'confirmado',
        'origem' => 'cliente',
        'valor_total' => 50,
    ]);

    $tentativa = fn () => $this->agendador->confirmar($this->cliente, $this->unidade->id, [$this->corte->id], $this->prof->id, $this->inicio);

    expect($tentativa)->toThrow(SlotIndisponivelException::class);

    // Continua só 1 agendamento ocupando o horário.
    $ocupando = Agendamento::where('profissional_id', $this->prof->id)
        ->where('data_hora_inicio', $this->inicio)
        ->ocupantes()->count();
    expect($ocupando)->toBe(1);
});

it('rejeita início no passado', function () {
    $passado = $this->dia->copy()->setTime(7, 0); // antes de agora (08:00)

    expect(fn () => $this->agendador->confirmar($this->cliente, $this->unidade->id, [$this->corte->id], $this->prof->id, $passado))
        ->toThrow(SlotIndisponivelException::class);
});

it('permite cancelar fora da janela de antecedência e libera o horário', function () {
    $ag = $this->agendador->confirmar($this->cliente, $this->unidade->id, [$this->corte->id], $this->prof->id, $this->inicio);

    expect($this->agendador->podeCancelar($ag))->toBeTrue(); // 10:00 está a >2h de 08:00

    $this->agendador->cancelar($ag);
    expect($ag->fresh()->status)->toBe('cancelado');

    // Horário volta a ficar livre.
    expect($this->agendador->confirmar($this->cliente, $this->unidade->id, [$this->corte->id], $this->prof->id, $this->inicio))
        ->toBeInstanceOf(Agendamento::class);
});

it('não permite cancelar dentro da antecedência mínima', function () {
    $perto = $this->dia->copy()->setTime(9, 0); // a 1h de agora (08:00), < 2h
    $ag = $this->agendador->confirmar($this->cliente, $this->unidade->id, [$this->corte->id], $this->prof->id, $perto);

    expect($this->agendador->podeCancelar($ag))->toBeFalse();
});
