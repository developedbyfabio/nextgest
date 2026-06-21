<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoria de produtos (banco do TENANT). Organização opcional dos produtos.
 * "Excluir" = inativar (`ativo = false`), seguindo o padrão de serviços.
 */
class CategoriaProduto extends Model
{
    protected $table = 'categorias_produto';

    protected $fillable = [
        'nome',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'categoria_id');
    }
}
