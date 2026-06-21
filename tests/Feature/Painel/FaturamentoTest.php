<?php

declare(strict_types=1);

use App\Models\Cliente;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\Venda;
use App\Services\Dashboard\Metricas;
use App\Services\Estoque\MovimentadorEstoque;
use App\Services\Venda\Comanda;
use Carbon\Carbon;

beforeEach(function () {
    $this->tenant = criarTenant('lojafat');
    tenancy()->initialize($this->tenant);

    Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00'));

    $this->unidadeA = Unidade::create(['nome' => 'A', 'ativo' => true]);
    $this->unidadeB = Unidade::create(['nome' => 'B', 'ativo' => true]);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45, 'ativo' => true]);
    $this->pomada = Produto::create(['nome' => 'Pomada', 'preco_venda' => 40, 'controla_estoque' => true, 'percentual_comissao' => 10, 'ativo' => true]);
    $this->prof = profissionalAgenda($this->unidadeA, [], [], ['name' => 'Jorge']);

    app(MovimentadorEstoque::class)->entrada($this->pomada->id, $this->unidadeA->id, 100);
    app(MovimentadorEstoque::class)->entrada($this->pomada->id, $this->unidadeB->id, 100);

    $this->comanda = app(Comanda::class);
});

afterEach(fn () => Carbon::setTestNow());

/** Cria uma venda PAGA com 1 serviço + (opcional) 1 produto, na data informada. */
function vendaPaga(int $unidadeId, bool $comProduto, ?Carbon $data = null): Venda
{
    $c = test()->comanda;
    $v = $c->abrir($unidadeId, null, test()->prof->id);
    $c->adicionarServico($v, test()->corte, test()->prof->id);
    if ($comProduto) {
        $c->adicionarProduto($v, test()->pomada, 1, test()->prof->id);
    }
    $c->pagar($v, test()->prof->id);

    if ($data) {
        $v->forceFill(['data' => $data])->save();
    }

    return $v->refresh();
}

it('faturamento = soma das vendas pagas no período e unidade (ignora o resto)', function () {
    // Conta: paga hoje na unidade A (45 + 40 = 85).
    vendaPaga($this->unidadeA->id, true, Carbon::now());

    // NÃO conta: aberta (sem pagar).
    $aberta = $this->comanda->abrir($this->unidadeA->id, null, $this->prof->id);
    $this->comanda->adicionarServico($aberta, $this->corte, $this->prof->id);

    // NÃO conta: cancelada (paga e depois cancelada).
    $cancel = vendaPaga($this->unidadeA->id, false, Carbon::now());
    $this->comanda->cancelar($cancel, $this->prof->id);

    // NÃO conta: fora do período (60 dias atrás).
    vendaPaga($this->unidadeA->id, false, Carbon::now()->copy()->subDays(60));

    // NÃO conta: outra unidade (B).
    vendaPaga($this->unidadeB->id, false, Carbon::now());

    $m = new Metricas(Carbon::now()->copy()->subDays(7)->startOfDay(), Carbon::now()->copy()->endOfDay(), $this->unidadeA->id);

    expect($m->faturamento())->toBe(85.0)
        ->and($m->vendasPagas())->toBe(1);
});

it('ticket médio = faturamento ÷ nº de vendas pagas', function () {
    vendaPaga($this->unidadeA->id, true, Carbon::now());   // 85
    vendaPaga($this->unidadeA->id, false, Carbon::now());  // 45

    $m = new Metricas(Carbon::now()->copy()->subDays(7)->startOfDay(), Carbon::now()->copy()->endOfDay(), $this->unidadeA->id);

    expect($m->faturamento())->toBe(130.0)
        ->and($m->vendasPagas())->toBe(2)
        ->and($m->ticketMedio())->toBe(65.0);
});

it('comissão a pagar = soma de valor_comissao das vendas pagas', function () {
    vendaPaga($this->unidadeA->id, true, Carbon::now());   // produto 40 × 10% = 4,00
    vendaPaga($this->unidadeA->id, true, Carbon::now());   // + 4,00
    vendaPaga($this->unidadeA->id, false, Carbon::now());  // só serviço, sem comissão

    $m = new Metricas(Carbon::now()->copy()->subDays(7)->startOfDay(), Carbon::now()->copy()->endOfDay(), $this->unidadeA->id);

    expect($m->comissaoAPagar())->toBe(8.0);
});

it('empty-state: sem vendas no período → tudo zero, sem inventar número', function () {
    // Existe uma venda paga, mas FORA do período consultado.
    vendaPaga($this->unidadeA->id, true, Carbon::now()->copy()->subDays(45));

    $m = new Metricas(Carbon::now()->copy()->subDays(7)->startOfDay(), Carbon::now()->copy()->endOfDay(), $this->unidadeA->id);

    expect($m->faturamento())->toBe(0.0)
        ->and($m->vendasPagas())->toBe(0)
        ->and($m->ticketMedio())->toBe(0.0)
        ->and($m->comissaoAPagar())->toBe(0.0)
        ->and($m->maisVendidos())->toHaveCount(0);
});

it('faturamento por dia: a soma da série bate com o faturamento do período', function () {
    vendaPaga($this->unidadeA->id, true, Carbon::now());                       // 85 hoje
    vendaPaga($this->unidadeA->id, false, Carbon::now()->copy()->subDays(2));  // 45 anteontem

    $m = new Metricas(Carbon::now()->copy()->subDays(6)->startOfDay(), Carbon::now()->copy()->endOfDay(), $this->unidadeA->id);
    $serie = $m->faturamentoPorDia();

    expect(array_sum($serie['valores']))->toBe(130.0)
        ->and($serie['labels'])->toHaveCount(7); // 7 dias (zerados onde não houve venda)
});

it('o dashboard mostra "Faturamento" real (sem rótulo estimado)', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@fat.test']);
    vendaPaga($this->unidadeA->id, true, Carbon::now());

    $html = $this->actingAs($dono, 'web')->get('/lojafat/painel')->assertOk()->content();

    expect($html)->toContain('Faturamento')
        ->and($html)->not->toContain('Faturamento estimado')
        ->and($html)->toContain('Ticket médio')
        ->and($html)->toContain('Comissão a pagar');
});
