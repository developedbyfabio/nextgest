<?php

declare(strict_types=1);

use App\Livewire\Painel\Indicadores;
use App\Models\Agendamento;
use App\Models\AssinaturaClube;
use App\Models\Cliente;
use App\Models\PlanoClube;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\Venda;
use App\Services\Agendamento\MotorDisponibilidade;
use App\Services\Clube\Assinaturas as AssinaturasClube;
use App\Services\Clube\IndicadoresClube;
use App\Services\Dashboard\Metricas;
use App\Services\Financeiro\ResumoFinanceiro;
use App\Services\Painel\IndicadoresClientes;
use App\Services\Painel\ResumoDoDia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

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

it('[PERF-002/003] índices de data existem (guarda contra remoção acidental)', function () {
    // Migração aditiva de tenant: agendamentos.data_hora_inicio e vendas.data.
    $colunasAg = collect(Schema::getIndexes('agendamentos'))->pluck('columns')->flatten();
    expect($colunasAg)->toContain('data_hora_inicio');

    $colunasVendas = collect(Schema::getIndexes('vendas'))->pluck('columns')->flatten();
    expect($colunasVendas)->toContain('data');

    // PERF-004: índices de busca/ordenação em clientes.
    $colunasClientes = collect(Schema::getIndexes('clientes'))->pluck('columns')->flatten();
    expect($colunasClientes)->toContain('nome')
        ->and($colunasClientes)->toContain('telefone');
});

it('[PERF] Motor com profissional FIXO: contagem baixa e constante', function () {
    $dia = Carbon::now()->next(Carbon::WEDNESDAY); // dia_semana 3
    $p = profissionalAgenda($this->unidade, [$this->servico], [[3, '09:00', '18:00']], ['email' => 'pf@x.test']);

    $motor = app(MotorDisponibilidade::class);
    $n = contarQueries(fn () => $motor->slots($this->unidade->id, [$this->servico->id], $p->id, $dia->copy()));

    expect($n)->toBeLessThanOrEqual(10); // caminho saudável
});

it('[PERF-001] Motor SEM preferência: contagem CONSTANTE (não cresce com nº de profissionais)', function () {
    $dia = Carbon::now()->next(Carbon::WEDNESDAY);
    $motor = app(MotorDisponibilidade::class);

    profissionalAgenda($this->unidade, [$this->servico], [[3, '09:00', '18:00']], ['email' => 'p1@x.test']);
    profissionalAgenda($this->unidade, [$this->servico], [[3, '09:00', '18:00']], ['email' => 'p2@x.test']);
    $com2 = contarQueries(fn () => $motor->slots($this->unidade->id, [$this->servico->id], null, $dia->copy()));

    foreach (['p3', 'p4', 'p5', 'p6'] as $e) {
        profissionalAgenda($this->unidade, [$this->servico], [[3, '09:00', '18:00']], ['email' => "{$e}@x.test"]);
    }
    $com6 = contarQueries(fn () => $motor->slots($this->unidade->id, [$this->servico->id], null, $dia->copy()));

    // Carga em lote (whereIn): a contagem NÃO muda com o nº de profissionais.
    // Vermelho se o N+1 voltar (com 6 prof o loop daria ~3x mais queries).
    expect($com6)->toBe($com2)
        ->and($com2)->toBeLessThanOrEqual(10);
});

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

it('[PERF] Resumo do dia: contagem de query CONSTANTE (agregados, não cresce com o volume)', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $cliente = Cliente::create(['nome' => 'C', 'email' => 'c@x.test', 'telefone' => '1', 'password' => 'x12345678']);
    // Dono que também atende: exercita os DOIS blocos (casa agregada + pessoal + próximo).
    $dono = usuarioComPapel('Dono', ['email' => 'dono@x.test', 'e_profissional' => true]);

    $criar = function (int $n) use ($cliente, $dono) {
        foreach (range(1, $n) as $i) {
            $ini = Carbon::today()->setTime(9, 0)->addMinutes($i); // hoje, futuro (após 08:00)
            Agendamento::create([
                'unidade_id' => $this->unidade->id, 'cliente_id' => $cliente->id, 'profissional_id' => $dono->id,
                'data_hora_inicio' => $ini, 'data_hora_fim' => $ini->copy()->addMinutes(30),
                'status' => 'pendente', 'origem' => 'cliente', 'valor_total' => 40,
            ]);
        }
    };

    $resumo = new ResumoDoDia($dono);
    $dono->can('ver_agenda'); // aquece o cache de permissões do spatie (carga única, fora da medição)

    $criar(3);
    $n3 = contarQueries(fn () => $resumo->dados());

    $criar(27); // total 30 agendamentos hoje
    $n30 = contarQueries(fn () => $resumo->dados());

    // CONSTANTE: a contagem NÃO muda com o nº de agendamentos (casa por agregado;
    // pessoal por count + 1 query do próximo). Vermelho se virar N+1.
    expect($n30)->toBe($n3)
        ->and($n3)->toBeLessThanOrEqual(5);

    Carbon::setTestNow();
});

it('[PERF] Indicadores: contagem de query CONSTANTE por métrica (não cresce com clientes/visitas)', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 22, 12, 0, 0));
    $svc = new IndicadoresClientes;

    $semear = function (int $nClientes) {
        foreach (range(1, $nClientes) as $i) {
            $cli = Cliente::create(['nome' => 'C'.uniqid(), 'telefone' => (string) random_int(1, 999999999)]);
            foreach ([90, 80, 70] as $d) { // 3 visitas pagas (intervalo 10, última 70d → risco/sempre)
                $data = Carbon::today()->subDays($d);
                Venda::create(['unidade_id' => $this->unidade->id, 'cliente_id' => $cli->id, 'status' => 'paga',
                    'valor_bruto' => 50, 'desconto' => 0, 'valor_total' => 50, 'data' => $data]);
            }
        }
    };

    $medir = fn (): array => [
        'risco' => contarQueries(fn () => $svc->emRisco(20)),
        'frequencia' => contarQueries(fn () => $svc->frequencia()),
        'ticket' => contarQueries(fn () => $svc->ticketMedio(Carbon::today()->subDays(60), Carbon::now(), null)),
        'bucket' => contarQueries(fn () => $svc->clientesPorBucket('sempre', 20)),
        'retencao' => contarQueries(fn () => $svc->retencao(Carbon::today()->subDays(30), Carbon::now())),
    ];

    $semear(5);
    $n5 = $medir();

    $semear(40); // total 45 clientes / 135 visitas pagas
    $n45 = $medir();

    // CONSTANTE: a contagem NÃO muda com o nº de clientes/visitas (vermelho = N+1).
    expect($n45)->toBe($n5)
        ->and($n5['risco'])->toBeLessThanOrEqual(2)        // paginate: count + select
        ->and($n5['frequencia'])->toBeLessThanOrEqual(1)
        ->and($n5['ticket'])->toBeLessThanOrEqual(1)
        ->and($n5['bucket'])->toBeLessThanOrEqual(2)
        ->and($n5['retencao'])->toBeLessThanOrEqual(1);

    Carbon::setTestNow();
});

it('[PERF] Aba Indicadores: contagem de query CONSTANTE (herda a eficiência do motor)', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 22, 12, 0, 0));

    $dono = usuarioComPapel('Dono', ['email' => 'dono@segperf.test']);
    $this->actingAs($dono, 'web');
    $dono->can('ver_indicadores'); // aquece o cache de permissões do spatie (carga única)

    $semear = function (int $nClientes) {
        foreach (range(1, $nClientes) as $i) {
            $cli = Cliente::create(['nome' => 'C'.uniqid(), 'telefone' => (string) random_int(1, 999999999)]);
            foreach ([90, 80, 70] as $d) { // 3 visitas pagas → risco + bucket "sempre"
                $data = Carbon::today()->subDays($d);
                Venda::create(['unidade_id' => $this->unidade->id, 'cliente_id' => $cli->id, 'status' => 'paga',
                    'valor_bruto' => 50, 'desconto' => 0, 'valor_total' => 50, 'data' => $data]);
            }
        }
    };

    // Render dos cards + drill-in de risco (exercita serviço + resolução de nomes da página).
    $render = fn () => Livewire::test(Indicadores::class)->call('abrirRisco');

    $semear(5);
    $n5 = contarQueries($render);

    $semear(40); // total 45 clientes / 135 visitas
    $n45 = contarQueries($render);

    // CONSTANTE: a aba não reintroduz N+1 — herda a eficiência do motor.
    expect($n45)->toBe($n5)
        ->and($n5)->toBeLessThanOrEqual(20);

    Carbon::setTestNow();
});

it('[PERF] Financeiro: contagem de query CONSTANTE (faturamento/recebimentos/série/CPV)', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 15, 12, 0, 0));
    $cliente = Cliente::create(['nome' => 'C', 'email' => 'c@x.test', 'telefone' => '1', 'password' => 'x12345678']);

    $semear = function (int $n) use ($cliente) {
        foreach (range(1, $n) as $i) {
            $venda = Venda::create(['unidade_id' => $this->unidade->id, 'cliente_id' => $cliente->id, 'status' => 'paga',
                'valor_bruto' => 60, 'desconto' => 0, 'valor_total' => 60, 'data' => Carbon::today()->subDays($i % 30)]);
            $venda->pagamentos()->create(['metodo' => 'pix', 'valor' => 60, 'status' => 'aprovado', 'pago_em' => Carbon::now()]);
        }
    };

    $fin = new ResumoFinanceiro(Carbon::today()->subDays(60)->startOfDay(), Carbon::today()->endOfDay());
    $medir = fn (): array => [
        'totais' => contarQueries(fn () => $fin->totais()),
        'comissoes' => contarQueries(fn () => $fin->comissoes()),
        'cpv' => contarQueries(fn () => $fin->cpv()),
        'recebimentos' => contarQueries(fn () => $fin->recebimentosPorForma()),
        'serie' => contarQueries(fn () => $fin->faturamentoPorDia()),
    ];

    $semear(5);
    $n5 = $medir();
    $semear(40);
    $n45 = $medir();

    expect($n45)->toBe($n5)
        ->and(array_sum($n5))->toBeLessThanOrEqual(6); // 1 por método, set-based

    Carbon::setTestNow();
});

it('[PERF] Indicadores do Clube: contagem de query CONSTANTE (não cresce com assinantes)', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 15, 12, 0, 0));
    $plano = PlanoClube::create(['nome' => 'VIP', 'preco_mensal' => 99.90, 'ativo' => true]);
    $svc = app(AssinaturasClube::class);
    $ind = new IndicadoresClube;

    $semear = function (int $n) use ($plano, $svc) {
        foreach (range(1, $n) as $i) {
            $cli = Cliente::create(['nome' => 'C'.uniqid(), 'telefone' => (string) random_int(1, 999999999)]);
            $a = $svc->criar($cli->id, $plano, AssinaturaClube::STATUS_ATIVA);
            if ($i % 5 === 0) {
                $svc->alterarStatus($a, AssinaturaClube::STATUS_CANCELADA);
            }
        }
    };

    $medir = fn (): array => [
        'ativos' => contarQueries(fn () => $ind->assinantesAtivos()),
        'novos' => contarQueries(fn () => $ind->novosNoMes()),
        'cancelados' => contarQueries(fn () => $ind->canceladosNoMes()),
        'inadimplentes' => contarQueries(fn () => $ind->inadimplentes(10)),
        'evolucao' => contarQueries(fn () => $ind->evolucao(6)),
    ];

    $semear(5);
    $n5 = $medir();
    $semear(40); // 45 assinaturas + eventos
    $n45 = $medir();

    expect($n45)->toBe($n5)
        ->and($n5['ativos'])->toBeLessThanOrEqual(1)
        ->and($n5['novos'])->toBeLessThanOrEqual(1)
        ->and($n5['cancelados'])->toBeLessThanOrEqual(1)
        ->and($n5['inadimplentes'])->toBeLessThanOrEqual(4)
        ->and($n5['evolucao'])->toBeLessThanOrEqual(1);

    Carbon::setTestNow();
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
