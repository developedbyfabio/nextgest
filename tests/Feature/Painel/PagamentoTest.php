<?php

declare(strict_types=1);

use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Estoque\MovimentadorEstoque;
use App\Services\Venda\Comanda;
use App\Services\Venda\PagamentoInvalidoException;
use Carbon\Carbon;

beforeEach(function () {
    $this->tenant = criarTenant('lojapag');
    tenancy()->initialize($this->tenant);
    Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00'));

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 60, 'ativo' => true]);
    $this->pomada = Produto::create(['nome' => 'Pomada', 'preco_venda' => 40, 'controla_estoque' => true, 'percentual_comissao' => 10, 'ativo' => true]);
    $this->prof = profissionalAgenda($this->unidade, [], [], ['name' => 'Jorge']);

    app(MovimentadorEstoque::class)->entrada($this->pomada->id, $this->unidade->id, 100);
    $this->comanda = app(Comanda::class);
});

afterEach(fn () => Carbon::setTestNow());

/** Comanda com 1 serviço (60) + 1 produto (40) = total 100. */
function comandaCem(): App\Models\Venda
{
    $v = test()->comanda->abrir(test()->unidade->id, null, test()->prof->id);
    test()->comanda->adicionarServico($v, test()->corte, test()->prof->id);
    test()->comanda->adicionarProduto($v, test()->pomada, 1, test()->prof->id);

    return $v->refresh();
}

it('pagamento presencial marca aprovado na hora (gateway nulo, pago_em, criado_por)', function () {
    $v = comandaCem();
    $this->comanda->pagarPresencial($v, [['metodo' => 'pix', 'valor' => 100]], $this->prof->id);

    $pg = $v->pagamentos()->first();
    expect($pg->status)->toBe('aprovado')
        ->and($pg->gateway_id)->toBeNull()
        ->and($pg->metodo)->toBe('pix')
        ->and((float) $pg->valor)->toBe(100.0)
        ->and($pg->pago_em)->not->toBeNull()
        ->and($pg->criado_por_user_id)->toBe($this->prof->id);
});

it('soma dos pagamentos = total libera a venda como paga e dispara baixa + comissão', function () {
    $v = comandaCem();
    $this->comanda->pagarPresencial($v, [['metodo' => 'dinheiro', 'valor' => 100]], $this->prof->id);

    $v->refresh();
    expect($v->status)->toBe('paga')
        // baixa de estoque: 100 → 99 (1 pomada)
        ->and(app(MovimentadorEstoque::class)->disponivel($this->pomada->id, $this->unidade->id))->toBe(99);

    // comissão do produto (40 × 10% = 4) gravada.
    expect((float) $v->itens()->where('tipo', 'produto')->first()->valor_comissao)->toBe(4.0);
});

it('pagamento dividido (N formas somando o total)', function () {
    $v = comandaCem();
    $this->comanda->pagarPresencial($v, [
        ['metodo' => 'dinheiro', 'valor' => 30],
        ['metodo' => 'pix', 'valor' => 70],
    ], $this->prof->id);

    $v->refresh();
    expect($v->status)->toBe('paga')
        ->and($v->pagamentos()->count())->toBe(2)
        ->and((float) $v->pagamentos()->sum('valor'))->toBe(100.0);
});

it('rejeita soma diferente do total (abaixo) e mantém a comanda aberta', function () {
    $v = comandaCem();

    expect(fn () => $this->comanda->pagarPresencial($v, [['metodo' => 'dinheiro', 'valor' => 80]], $this->prof->id))
        ->toThrow(PagamentoInvalidoException::class);

    $v->refresh();
    expect($v->status)->toBe('aberta')
        ->and($v->pagamentos()->count())->toBe(0)
        // não deu baixa de estoque
        ->and(app(MovimentadorEstoque::class)->disponivel($this->pomada->id, $this->unidade->id))->toBe(100);
});

it('rejeita soma acima do total (não grava valor acima do devido)', function () {
    $v = comandaCem();

    expect(fn () => $this->comanda->pagarPresencial($v, [['metodo' => 'dinheiro', 'valor' => 120]], $this->prof->id))
        ->toThrow(PagamentoInvalidoException::class);

    expect($v->fresh()->pagamentos()->count())->toBe(0);
});

it('rejeita forma de pagamento inválida', function () {
    $v = comandaCem();

    expect(fn () => $this->comanda->pagarPresencial($v, [['metodo' => 'cheque', 'valor' => 100]], $this->prof->id))
        ->toThrow(PagamentoInvalidoException::class);
});

it('cancelar venda paga marca os pagamentos como estornados', function () {
    $v = comandaCem();
    $this->comanda->pagarPresencial($v, [['metodo' => 'pix', 'valor' => 100]], $this->prof->id);

    $this->comanda->cancelar($v, $this->prof->id);

    $v->refresh();
    expect($v->status)->toBe('cancelada')
        ->and($v->pagamentos()->where('status', 'estornado')->count())->toBe(1)
        ->and($v->pagamentos()->where('status', 'aprovado')->count())->toBe(0)
        // estoque também estornado: 99 → 100
        ->and(app(MovimentadorEstoque::class)->disponivel($this->pomada->id, $this->unidade->id))->toBe(100);
});

it('o atalho pagar() registra um pagamento de dinheiro do total', function () {
    $v = comandaCem();
    $this->comanda->pagar($v, $this->prof->id);

    $pg = $v->pagamentos()->first();
    expect($v->fresh()->status)->toBe('paga')
        ->and($pg->metodo)->toBe('dinheiro')
        ->and((float) $pg->valor)->toBe(100.0)
        ->and($pg->status)->toBe('aprovado');
});
