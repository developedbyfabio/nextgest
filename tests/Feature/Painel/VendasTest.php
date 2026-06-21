<?php

declare(strict_types=1);

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\Venda;
use App\Services\Estoque\MovimentadorEstoque;
use App\Services\Venda\Comanda;
use App\Services\Venda\EstoqueInsuficienteException;
use Carbon\Carbon;

beforeEach(function () {
    $this->tenant = criarTenant('lojavenda');
    tenancy()->initialize($this->tenant);

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '11', 'email' => 'maria@v.test']);
    $this->prof = profissionalAgenda($this->unidade, [], [], ['name' => 'Jorge']);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45, 'ativo' => true]);
    $this->pomada = Produto::create(['nome' => 'Pomada', 'preco_venda' => 40, 'controla_estoque' => true, 'percentual_comissao' => 10, 'ativo' => true]);

    // Estoque inicial da pomada: 10.
    app(MovimentadorEstoque::class)->entrada($this->pomada->id, $this->unidade->id, 10);

    $this->comanda = app(Comanda::class);
});

it('abre comanda avulsa com totais zerados', function () {
    $v = $this->comanda->abrir($this->unidade->id, $this->cliente->id, $this->prof->id);

    expect($v->status)->toBe('aberta')
        ->and((float) $v->valor_total)->toBe(0.0)
        ->and($v->cliente_id)->toBe($this->cliente->id);
});

it('item grava snapshot de descrição e preço; subtotal = preço × quantidade', function () {
    $v = $this->comanda->abrir($this->unidade->id, null, $this->prof->id);
    $item = $this->comanda->adicionarProduto($v, $this->pomada, 2, $this->prof->id);

    // Snapshot não muda se o cadastro mudar depois.
    $this->pomada->update(['nome' => 'Pomada NOVA', 'preco_venda' => 99]);

    expect($item->descricao)->toBe('Pomada')
        ->and((float) $item->preco_unitario)->toBe(40.0)
        ->and((float) $item->subtotal)->toBe(80.0);
});

it('total = bruto − desconto (recalculado)', function () {
    $v = $this->comanda->abrir($this->unidade->id, null, $this->prof->id);
    $this->comanda->adicionarProduto($v, $this->pomada, 1, $this->prof->id); // 40
    $this->comanda->adicionarServico($v, $this->corte, $this->prof->id);     // 45
    $this->comanda->definirDesconto($v, 5);

    $v->refresh();
    expect((float) $v->valor_bruto)->toBe(85.0)
        ->and((float) $v->desconto)->toBe(5.0)
        ->and((float) $v->valor_total)->toBe(80.0);
});

it('pagar dá baixa de estoque (com venda_id) e calcula comissão (snapshot)', function () {
    $v = $this->comanda->abrir($this->unidade->id, $this->cliente->id, $this->prof->id);
    $this->comanda->adicionarProduto($v, $this->pomada, 3, $this->prof->id);
    $this->comanda->adicionarServico($v, $this->corte, $this->prof->id);

    $this->comanda->pagar($v, $this->prof->id);

    $v->refresh();
    expect($v->status)->toBe('paga');

    // Estoque baixou de 10 → 7.
    expect(app(MovimentadorEstoque::class)->disponivel($this->pomada->id, $this->unidade->id))->toBe(7);

    $saida = MovimentacaoEstoque::where('tipo', 'saida')->where('venda_id', $v->id)->first();
    expect($saida)->not->toBeNull()->and($saida->quantidade)->toBe(-3);

    // Comissão: produto 10% de 120 = 12; serviço sem comissão.
    $itemProduto = $v->itens()->where('tipo', 'produto')->first();
    $itemServico = $v->itens()->where('tipo', 'servico')->first();
    expect((float) $itemProduto->valor_comissao)->toBe(12.0)
        ->and((float) $itemProduto->percentual_comissao)->toBe(10.0)
        ->and($itemServico->valor_comissao)->toBeNull();
});

it('bloqueia vender produto acima do estoque da unidade', function () {
    $v = $this->comanda->abrir($this->unidade->id, null, $this->prof->id);

    expect(fn () => $this->comanda->adicionarProduto($v, $this->pomada, 11, $this->prof->id))
        ->toThrow(EstoqueInsuficienteException::class);

    // Não criou item.
    expect($v->itens()->count())->toBe(0);
});

it('cancelar venda paga estorna o estoque', function () {
    $v = $this->comanda->abrir($this->unidade->id, null, $this->prof->id);
    $this->comanda->adicionarProduto($v, $this->pomada, 4, $this->prof->id);
    $this->comanda->pagar($v, $this->prof->id);

    expect(app(MovimentadorEstoque::class)->disponivel($this->pomada->id, $this->unidade->id))->toBe(6);

    $this->comanda->cancelar($v, $this->prof->id);

    $v->refresh();
    expect($v->status)->toBe('cancelada')
        // Estoque voltou para 10.
        ->and(app(MovimentadorEstoque::class)->disponivel($this->pomada->id, $this->unidade->id))->toBe(10);

    $estorno = MovimentacaoEstoque::where('tipo', 'entrada')->where('venda_id', $v->id)->first();
    expect($estorno)->not->toBeNull()->and($estorno->quantidade)->toBe(4);
});

it('cancelar comanda aberta não mexe no estoque', function () {
    $v = $this->comanda->abrir($this->unidade->id, null, $this->prof->id);
    $this->comanda->adicionarProduto($v, $this->pomada, 2, $this->prof->id);

    $this->comanda->cancelar($v, $this->prof->id);

    expect($v->fresh()->status)->toBe('cancelada')
        ->and(app(MovimentadorEstoque::class)->disponivel($this->pomada->id, $this->unidade->id))->toBe(10); // intacto
});

it('comanda a partir de agendamento copia os serviços (snapshot) e não duplica', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-06 10:00:00'));

    $ag = Agendamento::create([
        'unidade_id' => $this->unidade->id,
        'cliente_id' => $this->cliente->id,
        'profissional_id' => $this->prof->id,
        'data_hora_inicio' => Carbon::now(),
        'data_hora_fim' => Carbon::now()->copy()->addMinutes(30),
        'status' => 'concluido',
        'origem' => 'equipe',
    ]);
    $ag->itens()->create(['servico_id' => $this->corte->id, 'preco' => 45, 'duracao_minutos' => 30]);

    $v = $this->comanda->apartirDeAgendamento($ag, $this->prof->id);

    expect($v->agendamento_id)->toBe($ag->id)
        ->and($v->itens()->count())->toBe(1);

    $item = $v->itens()->first();
    expect($item->tipo)->toBe('servico')
        ->and($item->descricao)->toBe('Corte')
        ->and((float) $item->preco_unitario)->toBe(45.0)
        ->and($item->profissional_id)->toBe($this->prof->id);

    // Idempotente: reabrir do mesmo agendamento devolve a mesma venda.
    $v2 = $this->comanda->apartirDeAgendamento($ag, $this->prof->id);
    expect($v2->id)->toBe($v->id)
        ->and(Venda::where('agendamento_id', $ag->id)->count())->toBe(1);

    Carbon::setTestNow();
});

it('não permite editar comanda já paga', function () {
    $v = $this->comanda->abrir($this->unidade->id, null, $this->prof->id);
    $this->comanda->adicionarServico($v, $this->corte, $this->prof->id);
    $this->comanda->pagar($v, $this->prof->id);

    expect(fn () => $this->comanda->adicionarServico($v, $this->corte, $this->prof->id))
        ->toThrow(App\Services\Venda\VendaNaoEditavelException::class);
});

it('Profissional sem criar_venda não acessa comandas (403)', function () {
    $prof = usuarioComPapel('Profissional', ['email' => 'profx@v.test', 'e_profissional' => true]);

    $this->actingAs($prof, 'web')->get('/lojavenda/painel/vendas')->assertForbidden();
});
