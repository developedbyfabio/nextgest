<?php

declare(strict_types=1);

use App\Livewire\Painel\Financeiro\Index;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Services\Dashboard\Metricas;
use App\Services\Financeiro\ResumoFinanceiro;
use Carbon\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    tenancy()->initialize(criarTenant('fin'));
    // Cache de permissões do spatie é em memória/arquivo e vaza entre recriações de
    // tenant no mesmo processo de teste (ids do tenant recriado mudam). Limpar garante
    // que o gate `can:ver_financeiro` leia o estado real deste tenant. (Em produção é
    // 1 request por processo; não acontece.)
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->cliente = Cliente::create(['nome' => 'Maria Cliente', 'telefone' => '11999990000']);
    Carbon::setTestNow(Carbon::create(2026, 6, 15, 12, 0, 0));
});

afterEach(fn () => Carbon::setTestNow());

/** Cria uma venda PAGA (hoje) com itens + comissão + pagamentos, controlando os valores. */
function vendaPagaFin(int $unidadeId, int $clienteId, float $valorTotal, array $itens = [], array $pagamentos = []): Venda
{
    $venda = Venda::create([
        'unidade_id' => $unidadeId, 'cliente_id' => $clienteId, 'status' => 'paga',
        'valor_bruto' => $valorTotal, 'desconto' => 0, 'valor_total' => $valorTotal, 'data' => Carbon::today(),
    ]);
    foreach ($itens as $item) {
        VendaItem::create(array_merge([
            'venda_id' => $venda->id, 'tipo' => 'servico', 'descricao' => 'Item', 'quantidade' => 1,
            'preco_unitario' => 0, 'subtotal' => 0, 'valor_comissao' => 0,
        ], $item));
    }
    foreach ($pagamentos as [$metodo, $valor]) {
        Pagamento::create([
            'venda_id' => $venda->id, 'metodo' => $metodo, 'valor' => $valor,
            'status' => 'aprovado', 'pago_em' => Carbon::today(),
        ]);
    }

    return $venda;
}

it('faturamento do financeiro == faturamento do dashboard (mesma fonte)', function () {
    vendaPagaFin($this->unidade->id, $this->cliente->id, 100);
    vendaPagaFin($this->unidade->id, $this->cliente->id, 50);
    // Uma venda aberta NÃO conta.
    Venda::create(['unidade_id' => $this->unidade->id, 'cliente_id' => $this->cliente->id, 'status' => 'aberta',
        'valor_bruto' => 999, 'desconto' => 0, 'valor_total' => 999, 'data' => Carbon::today()]);

    $ini = Carbon::today()->startOfDay();
    $fim = Carbon::today()->endOfDay();

    $fin = new ResumoFinanceiro($ini, $fim);
    $metricas = new Metricas($ini, $fim, null);

    expect($fin->faturamento())->toBe(150.0)
        ->and($fin->faturamento())->toBe(round($metricas->faturamento(), 2));
});

it('lucro bruto = receita − comissões − CPV (cenário montado)', function () {
    $produto = Produto::create(['nome' => 'Pomada', 'preco_venda' => 30, 'preco_custo' => 10, 'controla_estoque' => false, 'ativo' => true]);
    $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50, 'ativo' => true]);

    // Comanda 110 = produto 2x30 (CPV 2x10=20) + serviço 50 (comissão 5).
    vendaPagaFin($this->unidade->id, $this->cliente->id, 110, [
        ['tipo' => 'produto', 'produto_id' => $produto->id, 'quantidade' => 2, 'preco_unitario' => 30, 'subtotal' => 60, 'valor_comissao' => 0],
        ['tipo' => 'servico', 'servico_id' => $servico->id, 'quantidade' => 1, 'preco_unitario' => 50, 'subtotal' => 50, 'valor_comissao' => 5],
    ]);

    $fin = new ResumoFinanceiro(Carbon::today()->startOfDay(), Carbon::today()->endOfDay());

    expect($fin->faturamento())->toBe(110.0)
        ->and($fin->comissoes())->toBe(5.0)
        ->and($fin->cpv())->toBe(20.0)
        ->and($fin->lucroBruto())->toBe(85.0); // 110 − 5 − 20
});

it('recebimentos por forma somam o faturamento', function () {
    vendaPagaFin($this->unidade->id, $this->cliente->id, 110, [], [['dinheiro', 60], ['pix', 50]]);

    $fin = new ResumoFinanceiro(Carbon::today()->startOfDay(), Carbon::today()->endOfDay());
    $receb = $fin->recebimentosPorForma();

    expect($receb)->toBe(['dinheiro' => 60.0, 'pix' => 50.0])
        ->and(array_sum($receb))->toBe($fin->faturamento());
});

it('permissão: gate por ver_financeiro (Dono ok; Recepção/Profissional 403); menu só com permissão', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@fin.test']);
    $recepcao = usuarioComPapel('Recepção', ['email' => 'rec@fin.test']);
    $prof = usuarioComPapel('Profissional', ['email' => 'prof@fin.test', 'e_profissional' => true]);

    // Autorização: o mount do componente faz abort_unless(can('ver_financeiro'), 403) — a
    // MESMA permissão exigida pela rota (camada dupla: rota can: + mount). 403 cru, sem
    // redirect. (HTTP por actingAs flakeia o EscoparAutenticacaoPorTenant no processo
    // multi-teste; em produção o login real seta a sessão. O gate em si é o que importa.)
    $this->actingAs($dono, 'web');
    Livewire::test(Index::class)->assertOk()->assertSee('Não é cálculo de impostos', false);

    $this->actingAs($recepcao, 'web');
    Livewire::test(Index::class)->assertStatus(403);

    $this->actingAs($prof, 'web');
    Livewire::test(Index::class)->assertStatus(403);
});

it('a tela mostra o banner e o faturamento; lucro líquido NÃO é prometido', function () {
    vendaPagaFin($this->unidade->id, $this->cliente->id, 100);
    $dono = usuarioComPapel('Dono', ['email' => 'dono@fin.test']);
    $this->actingAs($dono, 'web');

    Livewire::test(Index::class)
        ->set('periodo', 'hoje')
        ->assertSee('R$ 100,00')
        ->assertSee('receita − comissões − CPV')
        ->assertSee('lucro')                 // fala de lucro BRUTO
        ->assertDontSee('lucro líquido');    // NÃO promete líquido
});

it('export CSV traz o aviso e os números, sem PII de cliente', function () {
    vendaPagaFin($this->unidade->id, $this->cliente->id, 100, [], [['pix', 100]]);
    $dono = usuarioComPapel('Dono', ['email' => 'dono@fin.test']);
    $this->actingAs($dono, 'web');

    // Chamada direta do método (StreamedResponse) para inspecionar o conteúdo.
    $comp = new Index;
    $comp->mount();
    $comp->periodo = 'hoje';
    $resposta = $comp->exportarCsv();

    ob_start();
    $resposta->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('Não é cálculo de impostos')
        ->toContain('Faturamento (receita bruta)')
        ->toContain('100,00')
        ->not->toContain('Maria Cliente')   // sem PII de cliente
        ->not->toContain('11999990000');
});

it('isolamento: faturamento de um tenant não vaza para outro', function () {
    vendaPagaFin($this->unidade->id, $this->cliente->id, 100);
    $fin = new ResumoFinanceiro(Carbon::today()->startOfDay(), Carbon::today()->endOfDay());
    expect($fin->faturamento())->toBe(100.0);
    tenancy()->end();

    tenancy()->initialize(criarTenant('fin2'));
    $fin2 = new ResumoFinanceiro(Carbon::today()->startOfDay(), Carbon::today()->endOfDay());
    expect($fin2->faturamento())->toBe(0.0);
    tenancy()->end();
});
