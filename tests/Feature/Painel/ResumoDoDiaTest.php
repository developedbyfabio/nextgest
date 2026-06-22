<?php

declare(strict_types=1);

use App\Livewire\Painel\ResumoDoDia;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Unidade;
use App\Services\Painel\ResumoDoDia as ResumoCalculo;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    tenancy()->initialize(criarTenant('resumo'));
    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->cliente = Cliente::create(['nome' => 'Maria Cliente', 'email' => 'm@x.test', 'telefone' => '1', 'password' => 'x12345678']);
    // Tempo congelado ao meio-dia de hoje: "08:00" é passado, "14:00" é futuro.
    Carbon::setTestNow(Carbon::today()->setTime(12, 0));
});

afterEach(function () {
    Carbon::setTestNow();
});

function agendarHoje(Unidade $unidade, Cliente $cliente, ?int $profId, string $hora, string $status = 'confirmado'): Agendamento
{
    $ini = Carbon::today()->setTimeFromTimeString($hora);

    return Agendamento::create([
        'unidade_id' => $unidade->id,
        'cliente_id' => $cliente->id,
        'profissional_id' => $profId,
        'data_hora_inicio' => $ini,
        'data_hora_fim' => $ini->copy()->addMinutes(30),
        'status' => $status,
        'origem' => 'cliente',
        'valor_total' => 40,
    ]);
}

it('profissional puro vê só o bloco pessoal (seus N de hoje + próximo)', function () {
    $jorge = usuarioComPapel('Profissional', ['email' => 'jorge@resumo.test', 'e_profissional' => true]);
    $outro = usuarioComPapel('Profissional', ['email' => 'ana@resumo.test', 'e_profissional' => true]);

    // 2 do Jorge (08h já passou, 14h é o próximo) + 1 de outro (não conta p/ ele).
    agendarHoje($this->unidade, $this->cliente, $jorge->id, '08:00');
    agendarHoje($this->unidade, $this->cliente, $jorge->id, '14:00');
    agendarHoje($this->unidade, $this->cliente, $outro->id, '09:30');

    $dados = (new ResumoCalculo($jorge))->dados();
    expect($dados['mostraPessoal'])->toBeTrue()
        ->and($dados['mostraCasa'])->toBeFalse()
        ->and($dados['meuTotal'])->toBe(2)
        ->and($dados['proximo']->data_hora_inicio->format('H:i'))->toBe('14:00');

    $this->actingAs($jorge, 'web');

    Livewire::test(ResumoDoDia::class)
        ->assertSee('Você tem 2 agendamentos hoje')
        ->assertSee('Próximo às 14:00')
        ->assertSee('Maria Cliente')
        ->assertDontSee('a confirmar')        // sem bloco da casa
        ->assertDontSee('Dia livre na agenda');
});

it('gestão (Gerente, ver_agenda) vê o resumo da casa (total + a confirmar), sem bloco pessoal', function () {
    $gerente = usuarioComPapel('Gerente', ['email' => 'ger@resumo.test', 'e_profissional' => false]);
    $prof = usuarioComPapel('Profissional', ['email' => 'p@resumo.test', 'e_profissional' => true]);

    agendarHoje($this->unidade, $this->cliente, $prof->id, '08:00', 'confirmado');
    agendarHoje($this->unidade, $this->cliente, $prof->id, '09:00', 'pendente');
    agendarHoje($this->unidade, $this->cliente, $prof->id, '10:00', 'pendente');
    agendarHoje($this->unidade, $this->cliente, $prof->id, '11:00', 'cancelado'); // livre: não conta

    $dados = (new ResumoCalculo($gerente))->dados();
    expect($dados['mostraCasa'])->toBeTrue()
        ->and($dados['mostraPessoal'])->toBeFalse()
        ->and($dados['casaTotal'])->toBe(3)        // 1 confirmado + 2 pendentes (cancelado fora)
        ->and($dados['casaPendentes'])->toBe(2);

    $this->actingAs($gerente, 'web');

    Livewire::test(ResumoDoDia::class)
        ->assertSee('3 agendamentos hoje')
        ->assertSee('2 a confirmar')
        ->assertDontSee('Você tem');           // sem bloco pessoal
});

it('Dono que também atende vê os DOIS blocos (casa + pessoal)', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@resumo.test', 'e_profissional' => true]);
    $prof = usuarioComPapel('Profissional', ['email' => 'p2@resumo.test', 'e_profissional' => true]);

    agendarHoje($this->unidade, $this->cliente, $dono->id, '14:00'); // dele (futuro)
    agendarHoje($this->unidade, $this->cliente, $prof->id, '08:00'); // da casa, não dele

    $dados = (new ResumoCalculo($dono))->dados();
    expect($dados['mostraCasa'])->toBeTrue()
        ->and($dados['mostraPessoal'])->toBeTrue()
        ->and($dados['casaTotal'])->toBe(2)
        ->and($dados['meuTotal'])->toBe(1);

    $this->actingAs($dono, 'web');

    Livewire::test(ResumoDoDia::class)
        ->assertSee('2 agendamentos hoje')            // casa
        ->assertSee('Você tem 1 agendamento hoje');   // pessoal (singular)
});

it('dia sem agendamentos: mensagem amigável, sem erro', function () {
    $gerente = usuarioComPapel('Gerente', ['email' => 'ger2@resumo.test', 'e_profissional' => false]);
    $this->actingAs($gerente, 'web');

    Livewire::test(ResumoDoDia::class)
        ->assertOk()
        ->assertSee('Nenhum agendamento para hoje');
});

it('profissional sem agendamentos hoje vê mensagem amigável pessoal', function () {
    $jorge = usuarioComPapel('Profissional', ['email' => 'jorge2@resumo.test', 'e_profissional' => true]);
    $this->actingAs($jorge, 'web');

    Livewire::test(ResumoDoDia::class)
        ->assertSee('Nenhum agendamento seu hoje');
});
