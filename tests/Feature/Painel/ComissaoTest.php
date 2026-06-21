<?php

declare(strict_types=1);

use App\Livewire\Painel\Comissoes\Index;
use App\Models\ComissaoProfissional;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Dashboard\Metricas;
use App\Services\Estoque\MovimentadorEstoque;
use App\Services\Venda\Comanda;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojacom');
    tenancy()->initialize($this->tenant);
    Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00'));

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    // Serviço com % padrão 40; produto com % padrão 10.
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 100, 'percentual_comissao' => 40, 'ativo' => true]);
    $this->semComissao = Servico::create(['nome' => 'Cortesia', 'duracao_minutos' => 10, 'preco' => 50, 'ativo' => true]);
    $this->pomada = Produto::create(['nome' => 'Pomada', 'preco_venda' => 50, 'controla_estoque' => true, 'percentual_comissao' => 10, 'ativo' => true]);
    $this->prof = profissionalAgenda($this->unidade, [], [], ['name' => 'Jorge']);

    app(MovimentadorEstoque::class)->entrada($this->pomada->id, $this->unidade->id, 100);
    $this->comanda = app(Comanda::class);
});

afterEach(fn () => Carbon::setTestNow());

it('aplica a % padrão do serviço ao pagar (snapshot)', function () {
    $v = $this->comanda->abrir($this->unidade->id, null, $this->prof->id);
    $this->comanda->adicionarServico($v, $this->corte, $this->prof->id); // 100 × 40%
    $this->comanda->pagar($v, $this->prof->id);

    $item = $v->itens()->first();
    expect((float) $item->percentual_comissao)->toBe(40.0)
        ->and((float) $item->valor_comissao)->toBe(40.0);
});

it('serviço sem % e sem override → sem comissão', function () {
    $v = $this->comanda->abrir($this->unidade->id, null, $this->prof->id);
    $this->comanda->adicionarServico($v, $this->semComissao, $this->prof->id);
    $this->comanda->pagar($v, $this->prof->id);

    $item = $v->itens()->first();
    expect($item->percentual_comissao)->toBeNull()
        ->and($item->valor_comissao)->toBeNull();
});

it('override do profissional tem precedência sobre a % padrão (serviço)', function () {
    ComissaoProfissional::create(['user_id' => $this->prof->id, 'servico_id' => $this->corte->id, 'percentual' => 55]);

    $v = $this->comanda->abrir($this->unidade->id, null, $this->prof->id);
    $this->comanda->adicionarServico($v, $this->corte, $this->prof->id); // 100 × 55% (override)
    $this->comanda->pagar($v, $this->prof->id);

    $item = $v->itens()->first();
    expect((float) $item->percentual_comissao)->toBe(55.0)
        ->and((float) $item->valor_comissao)->toBe(55.0);
});

it('override do profissional tem precedência sobre a % padrão (produto)', function () {
    ComissaoProfissional::create(['user_id' => $this->prof->id, 'produto_id' => $this->pomada->id, 'percentual' => 25]);

    $v = $this->comanda->abrir($this->unidade->id, null, $this->prof->id);
    $this->comanda->adicionarProduto($v, $this->pomada, 2, $this->prof->id); // subtotal 100 × 25%
    $this->comanda->pagar($v, $this->prof->id);

    $item = $v->itens()->where('tipo', 'produto')->first();
    expect((float) $item->percentual_comissao)->toBe(25.0)
        ->and((float) $item->valor_comissao)->toBe(25.0);
});

it('override não vaza para outro profissional', function () {
    $outro = profissionalAgenda($this->unidade, [], [], ['name' => 'Bruno', 'email' => 'bruno@com.test']);
    ComissaoProfissional::create(['user_id' => $this->prof->id, 'servico_id' => $this->corte->id, 'percentual' => 55]);

    $v = $this->comanda->abrir($this->unidade->id, null, $outro->id);
    $this->comanda->adicionarServico($v, $this->corte, $outro->id); // outro → usa padrão 40%
    $this->comanda->pagar($v, $outro->id);

    expect((float) $v->itens()->first()->percentual_comissao)->toBe(40.0);
});

it('relatório agrega comissão por profissional (vendas pagas no período)', function () {
    $v1 = $this->comanda->abrir($this->unidade->id, null, $this->prof->id);
    $this->comanda->adicionarServico($v1, $this->corte, $this->prof->id); // 40
    $this->comanda->pagar($v1, $this->prof->id);

    $v2 = $this->comanda->abrir($this->unidade->id, null, $this->prof->id);
    $this->comanda->adicionarProduto($v2, $this->pomada, 1, $this->prof->id); // 50 × 10% = 5
    $this->comanda->pagar($v2, $this->prof->id);

    $m = new Metricas(Carbon::now()->copy()->subDays(7)->startOfDay(), Carbon::now()->copy()->endOfDay(), null);
    $rel = $m->comissoesPorProfissional();

    expect($rel)->toHaveCount(1)
        ->and($rel->first()['nome'])->toBe('Jorge')
        ->and($rel->first()['total'])->toBe(45.0)   // 40 + 5
        ->and($rel->first()['itens'])->toBe(2);
});

it('salvar overrides cria e (em branco) remove', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@com.test']);
    Livewire::actingAs($dono, 'web');

    // Cria override de 55% para o Jorge no Corte.
    Livewire::test(Index::class)
        ->set('overrideProfId', (string) $this->prof->id)
        ->set('overrideServico.'.$this->corte->id, '55')
        ->call('salvarOverrides')
        ->assertHasNoErrors();

    expect((float) ComissaoProfissional::where('user_id', $this->prof->id)->where('servico_id', $this->corte->id)->value('percentual'))->toBe(55.0);

    // Em branco → remove.
    Livewire::test(Index::class)
        ->set('overrideProfId', (string) $this->prof->id)
        ->set('overrideServico.'.$this->corte->id, '')
        ->call('salvarOverrides');

    expect(ComissaoProfissional::where('user_id', $this->prof->id)->where('servico_id', $this->corte->id)->exists())->toBeFalse();
});

it('relatório de comissões exige ver_financeiro', function () {
    $gerente = usuarioComPapel('Gerente', ['email' => 'ger@com.test']);
    $this->actingAs($gerente, 'web')->get('/lojacom/painel/comissoes')->assertForbidden();

    $dono = usuarioComPapel('Dono', ['email' => 'dono2@com.test']);
    $this->actingAs($dono, 'web')->get('/lojacom/painel/comissoes')->assertOk();
});
