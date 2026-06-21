<?php

declare(strict_types=1);

use App\Livewire\Painel\Produtos\Index;
use App\Models\CategoriaProduto;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Models\ProdutoUnidade;
use App\Models\Unidade;
use App\Services\Estoque\MovimentadorEstoque;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojaprod');
    tenancy()->initialize($this->tenant);
    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->dono = usuarioComPapel('Dono', ['email' => 'dono@prod.test']);
});

function estoque(int $produtoId, int $unidadeId): int
{
    return (int) (ProdutoUnidade::where('produto_id', $produtoId)->where('unidade_id', $unidadeId)->value('quantidade') ?? 0);
}

it('cria produto e valida campos obrigatórios', function () {
    Livewire::actingAs($this->dono, 'web');

    // Validação: nome e preço de venda são obrigatórios.
    Livewire::test(Index::class)
        ->call('novo')
        ->set('nome', '')
        ->set('precoVenda', '')
        ->call('salvar')
        ->assertHasErrors(['nome', 'precoVenda']);

    Livewire::test(Index::class)
        ->call('novo')
        ->set('nome', 'Pomada')
        ->set('precoVenda', '39.90')
        ->set('controlaEstoque', true)
        ->call('salvar')
        ->assertHasNoErrors();

    $p = Produto::where('nome', 'Pomada')->first();
    expect($p)->not->toBeNull()
        ->and((float) $p->preco_venda)->toBe(39.90)
        ->and($p->controla_estoque)->toBeTrue();
});

it('edita produto', function () {
    $p = Produto::create(['nome' => 'Cera', 'preco_venda' => 20, 'controla_estoque' => false, 'ativo' => true]);

    Livewire::actingAs($this->dono, 'web');
    Livewire::test(Index::class)
        ->call('editar', $p->id)
        ->set('nome', 'Cera forte')
        ->set('precoVenda', '25')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($p->fresh()->nome)->toBe('Cera forte')
        ->and((float) $p->fresh()->preco_venda)->toBe(25.0);
});

it('inativar tira das listas ativas mas preserva (reativar)', function () {
    $p = Produto::create(['nome' => 'Óleo', 'preco_venda' => 30, 'ativo' => true]);

    Livewire::actingAs($this->dono, 'web');
    Livewire::test(Index::class)
        ->call('pedirInativar', $p->id)
        ->assertSet('confirmarInativar', $p->id)
        ->call('inativar', $p->id)
        ->assertSet('confirmarInativar', null);

    expect($p->fresh()->ativo)->toBeFalse()
        ->and(Produto::where('ativo', true)->where('id', $p->id)->exists())->toBeFalse()
        ->and(Produto::find($p->id))->not->toBeNull(); // não apagado

    Livewire::test(Index::class)->call('reativar', $p->id);
    expect($p->fresh()->ativo)->toBeTrue();
});

it('cria, renomeia e alterna categoria', function () {
    Livewire::actingAs($this->dono, 'web');

    Livewire::test(Index::class)
        ->call('abrirCategorias')
        ->set('categoriaNome', 'Bebidas')
        ->call('salvarCategoria')
        ->assertHasNoErrors();

    $cat = CategoriaProduto::where('nome', 'Bebidas')->first();
    expect($cat)->not->toBeNull()->and($cat->ativo)->toBeTrue();

    Livewire::test(Index::class)->call('editarCategoria', $cat->id)->set('categoriaNome', 'Bebidas geladas')->call('salvarCategoria');
    expect($cat->fresh()->nome)->toBe('Bebidas geladas');

    Livewire::test(Index::class)->call('alternarCategoria', $cat->id);
    expect($cat->fresh()->ativo)->toBeFalse();
});

it('entrada de estoque soma à quantidade da unidade', function () {
    $p = Produto::create(['nome' => 'Pomada', 'preco_venda' => 39.90, 'controla_estoque' => true, 'ativo' => true]);

    Livewire::actingAs($this->dono, 'web');
    Livewire::test(Index::class)
        ->call('abrirEstoque', $p->id)
        ->set('movUnidadeId', (string) $this->unidade->id)
        ->set('movTipo', 'entrada')
        ->set('movQuantidade', '10')
        ->call('registrarMovimentacao')
        ->assertHasNoErrors()
        ->set('movQuantidade', '5')
        ->call('registrarMovimentacao')
        ->assertHasNoErrors();

    expect(estoque($p->id, $this->unidade->id))->toBe(15);
    expect(MovimentacaoEstoque::where('produto_id', $p->id)->where('tipo', 'entrada')->count())->toBe(2);
});

it('ajuste define o total da unidade (delta sinalizado)', function () {
    $p = Produto::create(['nome' => 'Água', 'preco_venda' => 5, 'controla_estoque' => true, 'ativo' => true]);
    app(MovimentadorEstoque::class)->entrada($p->id, $this->unidade->id, 15);

    Livewire::actingAs($this->dono, 'web');
    Livewire::test(Index::class)
        ->call('abrirEstoque', $p->id)
        ->set('movUnidadeId', (string) $this->unidade->id)
        ->set('movTipo', 'ajuste')
        ->set('movQuantidade', '8')
        ->call('registrarMovimentacao')
        ->assertHasNoErrors();

    expect(estoque($p->id, $this->unidade->id))->toBe(8);

    $ultima = MovimentacaoEstoque::where('produto_id', $p->id)->latest('id')->first();
    expect($ultima->tipo)->toBe('ajuste')->and($ultima->quantidade)->toBe(-7);
});

it('serviço MovimentadorEstoque: entrada soma e ajuste define', function () {
    $p = Produto::create(['nome' => 'Cerveja', 'preco_venda' => 12, 'controla_estoque' => true, 'ativo' => true]);
    $mov = app(MovimentadorEstoque::class);

    $mov->entrada($p->id, $this->unidade->id, 30);
    expect(estoque($p->id, $this->unidade->id))->toBe(30);

    $mov->ajustePara($p->id, $this->unidade->id, 12);
    expect(estoque($p->id, $this->unidade->id))->toBe(12);

    // Estoque nunca fica negativo.
    $mov->aplicar($p->id, $this->unidade->id, 'saida', -100, 'teste');
    expect(estoque($p->id, $this->unidade->id))->toBe(0);
});

it('Profissional não acessa produtos (403)', function () {
    $prof = usuarioComPapel('Profissional', ['email' => 'prof@prod.test', 'e_profissional' => true]);

    $this->actingAs($prof, 'web')->get('/lojaprod/painel/produtos')->assertForbidden();
});

it('Recepção alcança a página para estoque, mas não edita o catálogo', function () {
    $rec = usuarioComPapel('Recepção', ['email' => 'rec@prod.test']);

    // Página carrega (tem gerir_estoque).
    $this->actingAs($rec, 'web')->get('/lojaprod/painel/produtos')->assertOk();

    Livewire::actingAs($rec, 'web');

    // Não pode criar/editar catálogo.
    Livewire::test(Index::class)->call('novo')->assertStatus(403);

    // Mas pode movimentar estoque.
    $p = Produto::create(['nome' => 'Shampoo', 'preco_venda' => 29.90, 'controla_estoque' => true, 'ativo' => true]);
    Livewire::test(Index::class)
        ->call('abrirEstoque', $p->id)
        ->set('movUnidadeId', (string) $this->unidade->id)
        ->set('movTipo', 'entrada')
        ->set('movQuantidade', '7')
        ->call('registrarMovimentacao')
        ->assertHasNoErrors();

    expect(estoque($p->id, $this->unidade->id))->toBe(7);
});
