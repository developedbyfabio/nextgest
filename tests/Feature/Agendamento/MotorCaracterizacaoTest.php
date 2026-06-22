<?php

declare(strict_types=1);

use App\Models\Agendamento;
use App\Models\Bloqueio;
use App\Models\Cliente;
use App\Models\ExcecaoFuncionamento;
use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Agendamento\Agendador;
use App\Services\Agendamento\MotorDisponibilidade;
use App\Services\Agendamento\SlotIndisponivelException;
use Carbon\Carbon;

/*
| CARACTERIZAÇÃO (golden master) do MotorDisponibilidade — captura a SAÍDA ATUAL
| (horas exatas, ordem, mapeamento de profissional) por cenário. É a rede de segurança
| do refactor PERF-001 (batch whereIn): a saída deve ficar IDÊNTICA byte a byte.
| Janela 09:00–12:00, serviço 60min, intervalo 15min → 9 slots (09:00..11:00).
*/

beforeEach(function () {
    $this->tenant = criarTenant('lojacarac');
    tenancy()->initialize($this->tenant);

    $this->agora = Carbon::parse('2026-07-06 08:00:00'); // segunda 08:00
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

/** Lista cheia esperada da janela 09:00–12:00 com serviço de 60min e passo 15min. */
function janelaCheia60(): array
{
    return ['09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45', '11:00'];
}

it('[CARAC] sem preferência, 3 profissionais livres: lista cheia e todos no 1º (alfabético)', function () {
    $ana = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Ana', 'email' => 'ana@l.test']);
    profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Bruno', 'email' => 'bruno@l.test']);
    profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Carlos', 'email' => 'carlos@l.test']);

    $slots = $this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia);

    expect($slots->pluck('hora')->all())->toBe(janelaCheia60())
        ->and($slots->pluck('profissional_id')->unique()->values()->all())->toBe([$ana->id]); // 1º livre por hora
});

it('[CARAC] sem preferência, Ana ocupada 09:00–10:00: cedo→Bruno, resto→Ana', function () {
    $ana = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Ana', 'email' => 'ana@l.test']);
    $bruno = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Bruno', 'email' => 'bruno@l.test']);

    Agendamento::create([
        'unidade_id' => $this->unidade->id, 'cliente_id' => $this->cliente->id, 'profissional_id' => $ana->id,
        'data_hora_inicio' => $this->dia->copy()->setTime(9, 0), 'data_hora_fim' => $this->dia->copy()->setTime(10, 0),
        'status' => 'confirmado', 'origem' => 'equipe', 'valor_total' => 50,
    ]);

    $slots = $this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia);
    $mapa = $slots->mapWithKeys(fn ($s) => [$s['hora'] => $s['profissional_id']])->all();

    expect($slots->pluck('hora')->all())->toBe(janelaCheia60())
        ->and($mapa['09:00'])->toBe($bruno->id)
        ->and($mapa['09:45'])->toBe($bruno->id)
        ->and($mapa['10:00'])->toBe($ana->id)
        ->and($mapa['11:00'])->toBe($ana->id);
});

it('[CARAC] profissional fixo, livre: lista cheia, todos o mesmo prof', function () {
    $ana = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Ana', 'email' => 'ana@l.test']);

    $slots = $this->motor->slots($this->unidade->id, [$this->corte->id], $ana->id, $this->dia);

    expect($slots->pluck('hora')->all())->toBe(janelaCheia60())
        ->and($slots->pluck('profissional_id')->unique()->values()->all())->toBe([$ana->id]);
});

it('[CARAC] agendamento 10:00–11:00 recorta: sobram 09:00 e 11:00', function () {
    $ana = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Ana', 'email' => 'ana@l.test']);

    Agendamento::create([
        'unidade_id' => $this->unidade->id, 'cliente_id' => $this->cliente->id, 'profissional_id' => $ana->id,
        'data_hora_inicio' => $this->dia->copy()->setTime(10, 0), 'data_hora_fim' => $this->dia->copy()->setTime(11, 0),
        'status' => 'confirmado', 'origem' => 'equipe', 'valor_total' => 50,
    ]);

    expect($this->motor->slots($this->unidade->id, [$this->corte->id], $ana->id, $this->dia)->pluck('hora')->all())
        ->toBe(['09:00', '11:00']);
});

it('[CARAC] bloqueio 09:00–10:00 recorta: sobram 10:00..11:00', function () {
    $ana = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Ana', 'email' => 'ana@l.test']);

    Bloqueio::create([
        'user_id' => $ana->id, 'inicio' => $this->dia->copy()->setTime(9, 0),
        'fim' => $this->dia->copy()->setTime(10, 0), 'motivo' => 'Reunião',
    ]);

    expect($this->motor->slots($this->unidade->id, [$this->corte->id], $ana->id, $this->dia)->pluck('hora')->all())
        ->toBe(['10:00', '10:15', '10:30', '10:45', '11:00']);
});

it('[CARAC] exceção FECHADO: zero slots', function () {
    profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Ana', 'email' => 'ana@l.test']);
    ExcecaoFuncionamento::create(['data' => $this->dia->toDateString(), 'tipo' => 'fechado', 'descricao' => 'Feriado']);

    expect($this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia)->all())->toBe([]);
});

it('[CARAC] exceção HORÁRIO ESPECIAL 10:00–12:00: recorta a faixa', function () {
    profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Ana', 'email' => 'ana@l.test']);
    ExcecaoFuncionamento::create(['data' => $this->dia->toDateString(), 'tipo' => 'horario_especial', 'hora_inicio' => '10:00', 'hora_fim' => '12:00']);

    expect($this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia)->pluck('hora')->all())
        ->toBe(['10:00', '10:15', '10:30', '10:45', '11:00']);
});

it('[CARAC] borda: profissional sem horário no dia → zero slots', function () {
    // Horário só para outro dia da semana (não o de hoje).
    $outroDia = ($this->diaSemana + 1) % 7;
    profissionalAgenda($this->unidade, [$this->corte], [[$outroDia, '09:00', '12:00']], ['name' => 'Ana', 'email' => 'ana@l.test']);

    expect($this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia)->all())->toBe([]);
});

it('[CARAC] borda: faixa totalmente lotada → zero slots', function () {
    $ana = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Ana', 'email' => 'ana@l.test']);
    Agendamento::create([
        'unidade_id' => $this->unidade->id, 'cliente_id' => $this->cliente->id, 'profissional_id' => $ana->id,
        'data_hora_inicio' => $this->dia->copy()->setTime(9, 0), 'data_hora_fim' => $this->dia->copy()->setTime(12, 0),
        'status' => 'confirmado', 'origem' => 'equipe', 'valor_total' => 50,
    ]);

    expect($this->motor->slots($this->unidade->id, [$this->corte->id], $ana->id, $this->dia)->all())->toBe([]);
});

it('[CARAC] múltiplos serviços (corte+barba=90min): último slot 10:30', function () {
    $barba = Servico::create(['nome' => 'Barba', 'duracao_minutos' => 30, 'preco' => 30, 'ativo' => true]);
    $barba->unidades()->sync([$this->unidade->id]);
    $ana = profissionalAgenda($this->unidade, [$this->corte, $barba], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Ana', 'email' => 'ana@l.test']);

    expect($this->motor->slots($this->unidade->id, [$this->corte->id, $barba->id], $ana->id, $this->dia)->pluck('hora')->all())
        ->toBe(['09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30']);
});

it('[CARAC] coerência com o Agendador: o slot ofertado é agendável e o lock barra dupla-marcação', function () {
    $ana = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']], ['name' => 'Ana', 'email' => 'ana@l.test']);
    $agendador = app(Agendador::class);

    $primeiro = $this->motor->slots($this->unidade->id, [$this->corte->id], $ana->id, $this->dia)->first();
    expect($primeiro)->not->toBeNull();

    // O slot ofertado é de fato agendável.
    $ag = $agendador->confirmar($this->cliente, $this->unidade->id, [$this->corte->id], $ana->id, $primeiro['inicio']->copy());
    expect($ag->exists)->toBeTrue();

    // Lock íntegro: re-agendar o MESMO horário falha (sem dupla-marcação).
    expect(fn () => $agendador->confirmar($this->cliente, $this->unidade->id, [$this->corte->id], $ana->id, $primeiro['inicio']->copy()))
        ->toThrow(SlotIndisponivelException::class);
});
