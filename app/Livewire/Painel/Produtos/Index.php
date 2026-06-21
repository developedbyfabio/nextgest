<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Produtos;

use App\Models\CategoriaProduto;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Models\ProdutoUnidade;
use App\Models\Unidade;
use App\Services\Estoque\MovimentadorEstoque;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Fatia 2A — catálogo de produtos, categorias e estoque por unidade (TENANT).
 * Permissões: criar_produto / editar_produto (catálogo, Dono/Gerente) e
 * gerir_estoque (movimentações, inclui Recepção). "Excluir" = inativar.
 */
#[Layout('components.layouts.painel')]
#[Title('Produtos')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    // Filtros da lista.
    public string $busca = '';

    public string $categoriaFiltro = '';

    public string $statusFiltro = 'ativos'; // ativos | inativos | todos

    // Modal de produto.
    public bool $mostrarForm = false;

    public ?int $editandoId = null;

    public string $nome = '';

    public ?string $categoriaId = null;

    public string $descricao = '';

    public ?string $sku = null;

    public ?string $precoVenda = null;

    public ?string $precoCusto = null;

    public bool $controlaEstoque = false;

    public ?string $percentualComissao = null;

    public bool $ativo = true;

    // Modal de categorias.
    public bool $mostrarCategorias = false;

    public ?int $categoriaEditId = null;

    public string $categoriaNome = '';

    // Confirmação de inativação.
    public ?int $confirmarInativar = null;

    // Modal de estoque.
    public bool $mostrarEstoque = false;

    public ?int $estoqueProdutoId = null;

    public ?string $movUnidadeId = null;

    public string $movTipo = 'entrada'; // entrada | ajuste

    public ?string $movQuantidade = null;

    public string $movMotivo = '';

    public function mount(): void
    {
        // Catálogo (editar_produto, Dono/Gerente) ou estoque (gerir_estoque, inclui
        // Recepção). As ações são reconferidas por permissão específica.
        $u = auth('web')->user();
        abort_unless($u && ($u->can('editar_produto') || $u->can('gerir_estoque')), 403);
    }

    public function updatedBusca(): void
    {
        $this->resetPage();
    }

    public function updatedCategoriaFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFiltro(): void
    {
        $this->resetPage();
    }

    // ----- Produto -----

    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'categoriaId' => ['nullable', 'integer', 'exists:categorias_produto,id'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'sku' => ['nullable', 'string', 'max:100'],
            'precoVenda' => ['required', 'numeric', 'min:0'],
            'precoCusto' => ['nullable', 'numeric', 'min:0'],
            'controlaEstoque' => ['boolean'],
            'percentualComissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ativo' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => 'Campo obrigatório.',
            'numeric' => 'Informe um número válido.',
        ];
    }

    public function novo(): void
    {
        $this->authorize('criar_produto');
        $this->resetForm();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $this->authorize('editar_produto');
        $p = Produto::findOrFail($id);

        $this->editandoId = $p->id;
        $this->nome = $p->nome;
        $this->categoriaId = $p->categoria_id ? (string) $p->categoria_id : null;
        $this->descricao = (string) $p->descricao;
        $this->sku = $p->sku;
        $this->precoVenda = (string) $p->preco_venda;
        $this->precoCusto = $p->preco_custo !== null ? (string) $p->preco_custo : null;
        $this->controlaEstoque = $p->controla_estoque;
        $this->percentualComissao = $p->percentual_comissao !== null ? (string) $p->percentual_comissao : null;
        $this->ativo = $p->ativo;
        $this->resetValidation();
        $this->mostrarForm = true;
    }

    public function salvar(): void
    {
        $this->authorize($this->editandoId ? 'editar_produto' : 'criar_produto');
        $dados = $this->validate();

        Produto::updateOrCreate(['id' => $this->editandoId], [
            'nome' => $dados['nome'],
            'categoria_id' => $dados['categoriaId'] ?: null,
            'descricao' => $dados['descricao'] ?: null,
            'sku' => $dados['sku'] ?: null,
            'preco_venda' => $dados['precoVenda'],
            'preco_custo' => $dados['precoCusto'] !== null && $dados['precoCusto'] !== '' ? $dados['precoCusto'] : null,
            'controla_estoque' => $dados['controlaEstoque'],
            'percentual_comissao' => $dados['percentualComissao'] !== null && $dados['percentualComissao'] !== '' ? $dados['percentualComissao'] : null,
            'ativo' => $dados['ativo'],
        ]);

        $this->mostrarForm = false;
        $this->resetForm();
        Flux::toast('Produto salvo.', variant: 'success');
    }

    public function pedirInativar(int $id): void
    {
        $this->authorize('editar_produto');
        $this->confirmarInativar = $id;
        Flux::modal('inativar-produto')->show();
    }

    public function inativar(int $id): void
    {
        $this->authorize('editar_produto');
        Produto::whereKey($id)->update(['ativo' => false]);
        $this->confirmarInativar = null;
        Flux::modal('inativar-produto')->close();
        Flux::toast('Produto inativado.');
    }

    public function reativar(int $id): void
    {
        $this->authorize('editar_produto');
        Produto::whereKey($id)->update(['ativo' => true]);
        Flux::toast('Produto reativado.', variant: 'success');
    }

    protected function resetForm(): void
    {
        $this->reset(['editandoId', 'nome', 'categoriaId', 'descricao', 'sku', 'precoVenda', 'precoCusto', 'controlaEstoque', 'percentualComissao']);
        $this->ativo = true;
        $this->resetValidation();
    }

    // ----- Categorias -----

    public function abrirCategorias(): void
    {
        $this->authorize('editar_produto');
        $this->reset(['categoriaEditId', 'categoriaNome']);
        $this->resetValidation();
        $this->mostrarCategorias = true;
    }

    public function salvarCategoria(): void
    {
        $this->authorize('editar_produto');
        $this->validate(['categoriaNome' => ['required', 'string', 'max:255']], attributes: ['categoriaNome' => 'nome']);

        CategoriaProduto::updateOrCreate(
            ['id' => $this->categoriaEditId],
            ['nome' => $this->categoriaNome, 'ativo' => true],
        );

        $this->reset(['categoriaEditId', 'categoriaNome']);
        Flux::toast('Categoria salva.', variant: 'success');
    }

    public function editarCategoria(int $id): void
    {
        $this->authorize('editar_produto');
        $cat = CategoriaProduto::findOrFail($id);
        $this->categoriaEditId = $cat->id;
        $this->categoriaNome = $cat->nome;
        $this->resetValidation();
    }

    public function alternarCategoria(int $id): void
    {
        $this->authorize('editar_produto');
        $cat = CategoriaProduto::findOrFail($id);
        $cat->update(['ativo' => ! $cat->ativo]);
        Flux::toast($cat->ativo ? 'Categoria reativada.' : 'Categoria inativada.');
    }

    // ----- Estoque -----

    public function abrirEstoque(int $id): void
    {
        $this->authorize('gerir_estoque');
        $produto = Produto::findOrFail($id);

        $this->estoqueProdutoId = $produto->id;
        $primeira = Unidade::where('ativo', true)->orderBy('nome')->first();
        $this->movUnidadeId = $primeira ? (string) $primeira->id : null;
        $this->movTipo = 'entrada';
        $this->movQuantidade = null;
        $this->movMotivo = '';
        $this->resetValidation();
        $this->mostrarEstoque = true;
    }

    public function registrarMovimentacao(MovimentadorEstoque $movimentador): void
    {
        $this->authorize('gerir_estoque');

        $dados = $this->validate([
            'movUnidadeId' => ['required', 'integer', 'exists:unidades,id'],
            'movTipo' => ['required', 'in:entrada,ajuste'],
            'movQuantidade' => ['required', 'integer', 'min:0'],
            'movMotivo' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'movUnidadeId' => 'unidade',
            'movQuantidade' => 'quantidade',
        ]);

        $produtoId = (int) $this->estoqueProdutoId;
        $unidadeId = (int) $dados['movUnidadeId'];
        $qtd = (int) $dados['movQuantidade'];
        $motivo = $dados['movMotivo'] ?: null;
        $userId = auth('web')->id();

        if ($dados['movTipo'] === 'entrada') {
            if ($qtd < 1) {
                $this->addError('movQuantidade', 'Para entrada, informe ao menos 1.');

                return;
            }
            $movimentador->entrada($produtoId, $unidadeId, $qtd, $motivo, $userId);
        } else {
            $movimentador->ajustePara($produtoId, $unidadeId, $qtd, $motivo, $userId);
        }

        $this->movQuantidade = null;
        $this->movMotivo = '';
        Flux::toast('Estoque atualizado.', variant: 'success');
    }

    public function render(): View
    {
        $produtos = Produto::query()
            ->with(['categoria:id,nome', 'estoques:id,produto_id,quantidade'])
            ->when($this->busca !== '', function ($q) {
                $termo = '%'.$this->busca.'%';
                $q->where(fn ($s) => $s->where('nome', 'like', $termo)->orWhere('sku', 'like', $termo));
            })
            ->when($this->categoriaFiltro !== '', fn ($q) => $q->where('categoria_id', (int) $this->categoriaFiltro))
            ->when($this->statusFiltro === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($this->statusFiltro === 'inativos', fn ($q) => $q->where('ativo', false))
            ->orderBy('nome')
            ->paginate(12);

        $produtoEstoque = $this->estoqueProdutoId
            ? Produto::with(['estoques.unidade:id,nome'])->find($this->estoqueProdutoId)
            : null;

        $movimentacoes = $this->estoqueProdutoId
            ? MovimentacaoEstoque::with(['unidade:id,nome', 'user:id,name'])
                ->where('produto_id', $this->estoqueProdutoId)
                ->latest()->limit(10)->get()
            : collect();

        return view('livewire.painel.produtos.index', [
            'produtos' => $produtos,
            'categorias' => CategoriaProduto::orderBy('nome')->get(),
            'unidades' => Unidade::where('ativo', true)->orderBy('nome')->get(),
            'podeGerirEstoque' => auth('web')->user()->can('gerir_estoque'),
            'estoquePorUnidade' => $produtoEstoque
                ? $produtoEstoque->estoques->keyBy('unidade_id')
                : collect(),
            'movimentacoes' => $movimentacoes,
        ]);
    }
}
