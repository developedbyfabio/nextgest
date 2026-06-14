<?php

declare(strict_types=1);

use App\Models\Agendamento;
use App\Models\Bloqueio;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Agendamento\MotorDisponibilidade;
use Carbon\Carbon;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);

    // "Agora" fixo numa segunda-feira de manhã.
    $this->agora = Carbon::parse('2026-07-06 08:00:00');
    Carbon::setTestNow($this->agora);
    $this->dia = $this->agora->copy();
    $this->diaSemana = (int) $this->dia->dayOfWeek;

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 60, 'preco' => 50, 'ativo' => true]);
    $this->corte->unidades()->sync([$this->unidade->id]);
    $this->cliente = Cliente::create(['nome' => 'Cli', 'telefone' => '1199', 'email' => 'cli@l.test']);
    $this->motor = app(MotorDisponibilidade::class);
});

afterEach(fn () => Carbon::setTestNow());

it('gera slots dentro da janela de trabalho', function () {
    profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']]);

    $horas = $this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia)->pluck('hora');

    expect($horas)->toContain('09:00', '11:00')
        ->and($horas)->not->toContain('11:15'); // 11:15+60 = 12:15 não cabe
});

it('subtrai um agendamento existente', function () {
    $prof = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']]);

    Agendamento::create([
        'unidade_id' => $this->unidade->id,
        'cliente_id' => $this->cliente->id,
        'profissional_id' => $prof->id,
        'data_hora_inicio' => $this->dia->copy()->setTime(10, 0),
        'data_hora_fim' => $this->dia->copy()->setTime(11, 0),
        'status' => 'confirmado',
        'origem' => 'equipe',
        'valor_total' => 50,
    ]);

    $horas = $this->motor->slots($this->unidade->id, [$this->corte->id], $prof->id, $this->dia)->pluck('hora');

    expect($horas)->toContain('09:00', '11:00')
        ->and($horas)->not->toContain('10:00', '10:30');
});

it('respeita o intervalo de almoço (duas faixas)', function () {
    profissionalAgenda($this->unidade, [$this->corte], [
        [$this->diaSemana, '09:00', '12:00'],
        [$this->diaSemana, '13:00', '18:00'],
    ]);

    $horas = $this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia)->pluck('hora');

    expect($horas)->toContain('11:00', '13:00')
        ->and($horas)->not->toContain('12:00', '12:30'); // almoço
});

it('não oferta horário que não cabe perto do fim', function () {
    $longo = Servico::create(['nome' => 'Combo', 'duracao_minutos' => 90, 'preco' => 90, 'ativo' => true]);
    $longo->unidades()->sync([$this->unidade->id]);
    profissionalAgenda($this->unidade, [$longo], [[$this->diaSemana, '09:00', '12:00']]);

    $horas = $this->motor->slots($this->unidade->id, [$longo->id], null, $this->dia)->pluck('hora');

    expect($horas)->toContain('10:30') // 10:30+90 = 12:00
        ->and($horas)->not->toContain('11:00'); // 11:00+90 = 12:30
});

it('oculta horários no passado para hoje', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-06 10:30:00'));
    profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']]);

    $horas = $this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia)->pluck('hora');

    expect($horas)->not->toContain('09:00', '10:00')
        ->and($horas)->toContain('11:00');
});

it('considera bloqueios pontuais', function () {
    $prof = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']]);

    Bloqueio::create([
        'user_id' => $prof->id,
        'inicio' => $this->dia->copy()->setTime(9, 0),
        'fim' => $this->dia->copy()->setTime(10, 0),
        'motivo' => 'Reunião',
    ]);

    $horas = $this->motor->slots($this->unidade->id, [$this->corte->id], $prof->id, $this->dia)->pluck('hora');

    expect($horas)->not->toContain('09:00', '09:30')
        ->and($horas)->toContain('10:00', '11:00');
});

it('sem preferência oferta o slot de qualquer profissional disponível', function () {
    $p1 = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Ana']);
    $p2 = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Bruno']);

    // Ana ocupada às 09:00–10:00; Bruno livre.
    Agendamento::create([
        'unidade_id' => $this->unidade->id,
        'cliente_id' => $this->cliente->id,
        'profissional_id' => $p1->id,
        'data_hora_inicio' => $this->dia->copy()->setTime(9, 0),
        'data_hora_fim' => $this->dia->copy()->setTime(10, 0),
        'status' => 'confirmado',
        'origem' => 'equipe',
        'valor_total' => 50,
    ]);

    $slots = $this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia);
    $slot0900 = $slots->firstWhere('hora', '09:00');

    expect($slot0900)->not->toBeNull()
        ->and($slot0900['profissional_id'])->toBe($p2->id); // só Bruno está livre às 09:00
});

it('exige que o profissional faça todos os serviços selecionados', function () {
    $barba = Servico::create(['nome' => 'Barba', 'duracao_minutos' => 30, 'preco' => 30, 'ativo' => true]);
    $barba->unidades()->sync([$this->unidade->id]);

    // Profissional só faz Corte (não faz Barba).
    profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']]);

    $slots = $this->motor->slots($this->unidade->id, [$this->corte->id, $barba->id], null, $this->dia);

    expect($slots)->toBeEmpty();
});
