<?php

declare(strict_types=1);

use App\Models\Cliente;
use App\Models\Unidade;
use App\Models\Venda;
use App\Services\Painel\IndicadoresClientes;
use Carbon\Carbon;

beforeEach(function () {
    tenancy()->initialize(criarTenant('indic'));
    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    // Tempo congelado p/ datas determinísticas (visitas à meia-noite → diffs em dias inteiros).
    Carbon::setTestNow(Carbon::create(2026, 6, 22, 12, 0, 0));
    $this->svc = new IndicadoresClientes;
});

afterEach(fn () => Carbon::setTestNow());

function novoCliente(string $nome): Cliente
{
    return Cliente::create(['nome' => $nome, 'telefone' => '1'.random_int(1000000, 9999999)]);
}

/** Cria uma comanda PAGA do cliente N dias atrás (meia-noite), valor opcional. */
function visitaPaga(int $unidadeId, int $clienteId, int $diasAtras, float $valor = 50.0): Venda
{
    $data = Carbon::today()->subDays($diasAtras);

    return Venda::create([
        'unidade_id' => $unidadeId,
        'cliente_id' => $clienteId,
        'status' => 'paga',
        'valor_bruto' => $valor,
        'desconto' => 0,
        'valor_total' => $valor,
        'data' => $data,
    ]);
}

it('risco: só clientes com >=3 visitas e atraso > intervalo médio x 1,5', function () {
    // A — SUMIDO: 3 visitas, intervalo 10d, última há 70d (70 > 10*1,5=15) → risco.
    $a = novoCliente('Sumido');
    foreach ([90, 80, 70] as $d) {
        visitaPaga($this->unidade->id, $a->id, $d);
    }

    // B — REGULAR em dia: 3 visitas, intervalo 7d, última há 7d (7 > 7*1,5=10,5? não) → fora.
    $b = novoCliente('EmDia');
    foreach ([21, 14, 7] as $d) {
        visitaPaga($this->unidade->id, $b->id, $d);
    }

    // C — POUCOS DADOS: 2 visitas → "novos", nunca risco.
    $c = novoCliente('Novato');
    foreach ([30, 10] as $d) {
        visitaPaga($this->unidade->id, $c->id, $d);
    }

    $risco = $this->svc->emRisco(20);

    expect($risco->total())->toBe(1)
        ->and((int) $risco->items()[0]->cliente_id)->toBe($a->id)
        ->and((int) round($risco->items()[0]->intervalo_medio))->toBe(10)
        ->and((int) round($risco->items()[0]->dias_desde_ultima))->toBe(70);
});

it('frequência: bucketiza por intervalo médio e separa < 3 visitas em "novos"', function () {
    // sempre (≤14): intervalo 7
    $s = novoCliente('Sempre');
    foreach ([21, 14, 7] as $d) {
        visitaPaga($this->unidade->id, $s->id, $d);
    }
    // regular (>14, ≤35): intervalo 30
    $r = novoCliente('Regular');
    foreach ([90, 60, 30] as $d) {
        visitaPaga($this->unidade->id, $r->id, $d);
    }
    // esporádico (>35): intervalo 50
    $e = novoCliente('Esporadico');
    foreach ([150, 100, 50] as $d) {
        visitaPaga($this->unidade->id, $e->id, $d);
    }
    // novos (<3 visitas)
    $n = novoCliente('Novato');
    visitaPaga($this->unidade->id, $n->id, 5);

    expect($this->svc->frequencia())->toBe([
        'sempre' => 1,
        'regular' => 1,
        'esporadico' => 1,
        'novos' => 1,
    ]);

    // Listagem por bucket (paginada) traz o cliente certo.
    $regulares = $this->svc->clientesPorBucket('regular', 20);
    expect($regulares->total())->toBe(1)
        ->and((int) $regulares->items()[0]->cliente_id)->toBe($r->id);
});

it('clientesPorBucket rejeita bucket inválido', function () {
    expect(fn () => $this->svc->clientesPorBucket('inexistente'))
        ->toThrow(InvalidArgumentException::class);
});

it('ticket médio: média das comandas pagas, com recorte por período e profissional', function () {
    $cli = novoCliente('Cli');

    // 3 pagas (40, 60, 80) → média 60.
    visitaPaga($this->unidade->id, $cli->id, 5, 40);
    visitaPaga($this->unidade->id, $cli->id, 4, 60);
    visitaPaga($this->unidade->id, $cli->id, 3, 80);
    // Uma comanda ABERTA não conta.
    Venda::create(['unidade_id' => $this->unidade->id, 'cliente_id' => $cli->id, 'status' => 'aberta',
        'valor_bruto' => 1000, 'desconto' => 0, 'valor_total' => 1000, 'data' => Carbon::today()]);

    expect($this->svc->ticketMedio())->toBe(60.0);

    // Recorte por período: só a de 3 dias atrás (80).
    expect($this->svc->ticketMedio(Carbon::today()->subDays(3)->startOfDay(), Carbon::now()))->toBe(80.0);

    // Recorte por profissional sem comandas → 0.
    expect($this->svc->ticketMedio(null, null, 999))->toBe(0.0);
});

it('retenção: clientes do período anterior que voltaram no atual ÷ base do anterior', function () {
    $atualInicio = Carbon::today()->subDays(30);
    $atualFim = Carbon::now();
    // anterior = [-60, -30)

    $x = novoCliente('Voltou');     // anterior(-45) + atual(-10) → base e voltou
    visitaPaga($this->unidade->id, $x->id, 45);
    visitaPaga($this->unidade->id, $x->id, 10);

    $y = novoCliente('Sumiu');      // só anterior(-45) → base, não voltou
    visitaPaga($this->unidade->id, $y->id, 45);

    $z = novoCliente('Novo');       // só atual(-10) → não entra na base
    visitaPaga($this->unidade->id, $z->id, 10);

    $ret = $this->svc->retencao($atualInicio, $atualFim);

    expect($ret['base'])->toBe(2)
        ->and($ret['voltaram'])->toBe(1)
        ->and($ret['taxa'])->toBe(50.0);
});

it('clientes sem comanda paga não entram em nenhuma métrica de hábito', function () {
    $cli = novoCliente('SemPaga');
    // só comanda aberta (não paga)
    Venda::create(['unidade_id' => $this->unidade->id, 'cliente_id' => $cli->id, 'status' => 'aberta',
        'valor_bruto' => 100, 'desconto' => 0, 'valor_total' => 100, 'data' => Carbon::today()]);

    expect($this->svc->frequencia())->toBe(['sempre' => 0, 'regular' => 0, 'esporadico' => 0, 'novos' => 0])
        ->and($this->svc->emRisco()->total())->toBe(0);
});
