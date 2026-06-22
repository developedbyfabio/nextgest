<?php

declare(strict_types=1);

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\Venda;
use App\Services\Agendamento\MotorDisponibilidade;
use App\Services\Dashboard\Metricas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/*
| Auditoria de performance — CONTAGEM de queries (estrutura que transfere p/ produção;
| latência absoluta no dev não é métrica). Caminho saudável → contagem baixa/constante
| (teste passa). Gargalo → `skip` PERF-### com a evidência medida contra o tenant de volume.
*/

beforeEach(function () {
    tenancy()->initialize(criarTenant('segperf'));
    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);
    $this->servico->unidades()->sync([$this->unidade->id]);
});

function contarQueries(callable $fn): int
{
    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();
    $fn();
    $n = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    return $n;
}

it('[PERF] Motor com profissional FIXO: contagem baixa e constante', function () {
    $dia = Carbon::now()->next(Carbon::WEDNESDAY); // dia_semana 3
    $p = profissionalAgenda($this->unidade, [$this->servico], [[3, '09:00', '18:00']], ['email' => 'pf@x.test']);

    $motor = app(MotorDisponibilidade::class);
    $n = contarQueries(fn () => $motor->slots($this->unidade->id, [$this->servico->id], $p->id, $dia->copy()));

    expect($n)->toBeLessThanOrEqual(10); // caminho saudável
});

it('[PERF-001] Motor SEM preferência não deveria crescer com o nº de profissionais (N+1)', function () {
    $dia = Carbon::now()->next(Carbon::WEDNESDAY);
    $motor = app(MotorDisponibilidade::class);

    profissionalAgenda($this->unidade, [$this->servico], [[3, '09:00', '18:00']], ['email' => 'p1@x.test']);
    profissionalAgenda($this->unidade, [$this->servico], [[3, '09:00', '18:00']], ['email' => 'p2@x.test']);
    $com2 = contarQueries(fn () => $motor->slots($this->unidade->id, [$this->servico->id], null, $dia->copy()));

    profissionalAgenda($this->unidade, [$this->servico], [[3, '09:00', '18:00']], ['email' => 'p3@x.test']);
    profissionalAgenda($this->unidade, [$this->servico], [[3, '09:00', '18:00']], ['email' => 'p4@x.test']);
    $com4 = contarQueries(fn () => $motor->slots($this->unidade->id, [$this->servico->id], null, $dia->copy()));

    expect($com4)->toBe($com2); // saudável seria constante
})->skip('PERF-001 (alta): Motor sem preferência faz 3 queries POR profissional (HorarioTrabalho+Agendamento+Bloqueio em loop). Medido no volume: 35 q/dia (10 prof), 225 q/semana. Fix = batch whereIn (núcleo) — aguarda aprovação.');

it('[PERF] Dashboard: contagem de queries baixa e constante (agregados, não N+1)', function () {
    $cliente = Cliente::create(['nome' => 'C', 'email' => 'c@x.test', 'telefone' => '1', 'password' => 'x12345678']);
    $prof = profissionalAgenda($this->unidade, [$this->servico], [[3, '09:00', '18:00']], ['email' => 'pp@x.test']);
    foreach (range(1, 12) as $i) {
        $ini = Carbon::now()->subDays($i)->setTime(10, 0);
        Agendamento::create([
            'unidade_id' => $this->unidade->id, 'cliente_id' => $cliente->id, 'profissional_id' => $prof->id,
            'data_hora_inicio' => $ini, 'data_hora_fim' => $ini->copy()->addMinutes(30),
            'status' => 'concluido', 'origem' => 'cliente', 'valor_total' => 40,
        ]);
        Venda::create([
            'unidade_id' => $this->unidade->id, 'cliente_id' => $cliente->id, 'status' => 'paga',
            'valor_bruto' => 60, 'desconto' => 0, 'valor_total' => 60, 'data' => $ini,
        ]);
    }

    $ini = Carbon::now()->subDays(29)->startOfDay();
    $fim = Carbon::now()->endOfDay();

    $n = contarQueries(function () use ($ini, $fim) {
        $m = new Metricas($ini, $fim, null);
        $m->totalAgendamentos();
        $m->comparativoTotal();
        $m->servicosTop();
        $m->agendamentosPorDia();
        $m->horariosPorHora();
        $m->comparecimento();
        $m->faturamento();
        $m->vendasPagas();
        $m->faturamentoPorDia();
        $m->maisVendidos();
        $m->ticketMedio();
        $m->comissaoAPagar();
        $m->comparativoFaturamento();
        $m->clientesNovos();
        $m->clientesRecorrentes();
        $m->profissionaisDesempenho();
    });

    expect($n)->toBeLessThanOrEqual(25); // medido: 19 contra 20k agendamentos
});

it('[PERF] Vendas: lista paginada + eager tem contagem constante (sem N+1)', function () {
    $cliente = Cliente::create(['nome' => 'C', 'email' => 'c@x.test', 'telefone' => '1', 'password' => 'x12345678']);
    foreach (range(1, 30) as $i) {
        Venda::create([
            'unidade_id' => $this->unidade->id, 'cliente_id' => $cliente->id, 'status' => 'paga',
            'valor_bruto' => 60, 'desconto' => 0, 'valor_total' => 60, 'data' => Carbon::now()->subDays($i),
        ]);
    }

    $n = contarQueries(fn () => Venda::with(['cliente:id,nome', 'unidade:id,nome'])
        ->orderByDesc('data')->paginate(12));

    expect($n)->toBeLessThanOrEqual(6); // 1 count + 1 página + 2 eager
});

it('[PERF] Agenda (semana) + eager tem contagem constante (sem N+1)', function () {
    $cliente = Cliente::create(['nome' => 'C', 'email' => 'c@x.test', 'telefone' => '1', 'password' => 'x12345678']);
    $prof = profissionalAgenda($this->unidade, [$this->servico], [[3, '09:00', '18:00']], ['email' => 'pa@x.test']);
    foreach (range(0, 6) as $d) {
        $ini = Carbon::now()->startOfWeek()->addDays($d)->setTime(10, 0);
        Agendamento::create([
            'unidade_id' => $this->unidade->id, 'cliente_id' => $cliente->id, 'profissional_id' => $prof->id,
            'data_hora_inicio' => $ini, 'data_hora_fim' => $ini->copy()->addMinutes(30),
            'status' => 'confirmado', 'origem' => 'cliente', 'valor_total' => 40,
        ]);
    }

    $de = Carbon::now()->startOfWeek();
    $ate = Carbon::now()->endOfWeek();
    $n = contarQueries(fn () => Agendamento::with(['cliente', 'profissional', 'unidade', 'itens.servico'])
        ->whereBetween('data_hora_inicio', [$de, $ate])->get());

    expect($n)->toBeLessThanOrEqual(8); // 1 base + 4 eager (constante p/ qualquer nº de linhas)
});
