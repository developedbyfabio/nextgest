<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produto vendido pelo estabelecimento (banco do TENANT).
 *
 * Estoque é OPCIONAL (`controla_estoque`) e fica POR UNIDADE (pivô
 * `produto_unidade` com `quantidade`). "Excluir" = inativar (`ativo = false`),
 * como em serviços. A venda (baixa de estoque) chega na Fatia 2B.
 */
class Produto extends Model
{
    protected $table = 'produtos';

    protected $fillable = [
        'categoria_id',
        'nome',
        'descricao',
        'sku',
        'preco_venda',
        'preco_custo',
        'controla_estoque',
        'percentual_comissao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'preco_venda' => 'decimal:2',
            'preco_custo' => 'decimal:2',
            'percentual_comissao' => 'decimal:2',
            'controla_estoque' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProduto::class, 'categoria_id');
    }

    /** Estoque por filial (pivô com a quantidade). */
    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'produto_unidade')
            ->withPivot('quantidade');
    }

    public function estoques(): HasMany
    {
        return $this->hasMany(ProdutoUnidade::class, 'produto_id');
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoEstoque::class, 'produto_id');
    }

    /** Soma do estoque em todas as filiais (0 quando não controla estoque). */
    protected function estoqueTotal(): Attribute
    {
        return Attribute::get(fn () => (int) $this->estoques->sum('quantidade'));
    }
}
