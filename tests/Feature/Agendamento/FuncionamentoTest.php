<?php

declare(strict_types=1);

use App\Livewire\Painel\Funcionamento\Index as Funcionamento;
use App\Models\Configuracao;
use App\Models\ExcecaoFuncionamento;
use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Agendamento\MotorDisponibilidade;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojafunc');
    tenancy()->initialize($this->tenant);

    // "Agora" fixo numa segunda de manhã.
    $this->agora = Carbon::parse('2026-07-06 08:00:00');
    Carbon::setTestNow($this->agora);
    $this->dia = $this->agora->copy();
    $this->diaSemana = (int) $this->dia->dayOfWeek; // 1 = segunda

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 60, 'preco' => 50, 'ativo' => true]);
    $this->corte->unidades()->sync([$this->unidade->id]);
    $this->prof = profissionalAgenda($this->unidade, [$this->corte], [[$this->diaSemana, '09:00', '12:00']]);
    $this->motor = app(MotorDisponibilidade::class);
});

afterEach(fn () => Carbon::setTestNow());

/** Define o horário semanal (Configuracao) com uma faixa para o dia da semana dado. */
function definirHorarioSemanal(int $diaAberto, ?string $ini = '09:00', ?string $fim = '18:00', bool $aberto = true): void
{
    $lista = [];
    foreach ([0, 1, 2, 3, 4, 5, 6] as $d) {
        $lista[] = ['dia' => $d, 'aberto' => $d === $diaAberto ? $aberto : true, 'inicio' => $ini, 'fim' => $fim];
    }
    Configuracao::updateOrCreate(['chave' => 'horario_funcionamento'], ['valor' => json_encode($lista)]);
}

it('SEM horário configurado, a disponibilidade é a de sempre (permissivo)', function () {
    $horas = $this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia)->pluck('hora');

    expect($horas)->toContain('09:00', '10:00', '11:00');
});

it('horário semanal FECHANDO o dia → motor não gera slots (Tarefa A)', function () {
    definirHorarioSemanal($this->diaSemana, aberto: false);

    $horas = $this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia);

    expect($horas)->toBeEmpty();
});

it('exceção FECHADO no dia → zero slots', function () {
    definirHorarioSemanal($this->diaSemana, '09:00', '18:00'); // dia aberto normalmente
    ExcecaoFuncionamento::create(['data' => $this->dia->toDateString(), 'tipo' => 'fechado', 'descricao' => 'Feriado']);

    expect($this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia))->toBeEmpty();
});

it('exceção HORÁRIO ESPECIAL → slots só dentro da faixa', function () {
    definirHorarioSemanal($this->diaSemana, '09:00', '18:00');
    ExcecaoFuncionamento::create([
        'data' => $this->dia->toDateString(),
        'tipo' => 'horario_especial',
        'hora_inicio' => '10:00',
        'hora_fim' => '11:00',
    ]);

    $horas = $this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia)->pluck('hora');

    expect($horas)->toContain('10:00')              // 10:00–11:00 cabe
        ->and($horas)->not->toContain('09:00')      // antes da faixa
        ->and($horas)->not->toContain('11:00');     // 11:00+60 estoura a faixa
});

it('dia SEM exceção continua normal (exceção é só naquele dia)', function () {
    // Exceção fechado num OUTRO dia futuro; o dia atual segue normal.
    ExcecaoFuncionamento::create(['data' => $this->dia->copy()->addDays(7)->toDateString(), 'tipo' => 'fechado']);

    $horas = $this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia)->pluck('hora');

    expect($horas)->toContain('09:00', '10:00', '11:00');
});

it('intervaloAgendavel respeita exceção fechado (Agendador/lock)', function () {
    ExcecaoFuncionamento::create(['data' => $this->dia->toDateString(), 'tipo' => 'fechado']);

    $inicio = $this->dia->copy()->setTime(10, 0);
    $fim = $inicio->copy()->addMinutes(60);

    expect($this->motor->intervaloAgendavel($this->unidade->id, $this->prof->id, $inicio, $fim))->toBeFalse();
});

it('a tela de Funcionamento salva o horário e o motor passa a refletir', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    $comp = Livewire::test(Funcionamento::class);
    // Fecha o dia da semana atual e salva.
    foreach ($comp->get('funcionamento') as $i => $f) {
        if ((int) $f['dia'] === $this->diaSemana) {
            $comp->set("funcionamento.$i.aberto", false);
        }
    }
    $comp->call('salvarHorario')->assertHasNoErrors();

    expect($this->motor->slots($this->unidade->id, [$this->corte->id], null, $this->dia))->toBeEmpty();
});

it('a tela de Funcionamento cria/edita/remove exceção', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    $data = $this->dia->copy()->addDays(3)->toDateString();

    Livewire::test(Funcionamento::class)
        ->call('abrirExcecao', $data)
        ->set('excecaoTipo', 'horario_especial')
        ->set('excecaoInicio', '13:00')
        ->set('excecaoFim', '16:00')
        ->set('excecaoDescricao', 'Meio período')
        ->call('salvarExcecao')
        ->assertHasNoErrors();

    $exc = ExcecaoFuncionamento::whereDate('data', $data)->first();
    expect($exc)->not->toBeNull()
        ->and($exc->tipo)->toBe('horario_especial')
        ->and(substr((string) $exc->hora_inicio, 0, 5))->toBe('13:00');

    Livewire::test(Funcionamento::class)->call('removerExcecao', $exc->id);
    expect(ExcecaoFuncionamento::whereDate('data', $data)->exists())->toBeFalse();
});

it('Funcionamento exige gerir_agenda (Profissional 403)', function () {
    $this->actingAs(usuarioComPapel('Profissional', ['e_profissional' => true]), 'web')
        ->get('/lojafunc/painel/funcionamento')
        ->assertForbidden();
});
