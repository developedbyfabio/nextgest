<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estoque de um produto numa unidade/filial (banco do TENANT). Linha do pivô
 * `produto_unidade` com a `quantidade` atual. Sem timestamps (a tabela não os tem).
 */
class ProdutoUnidade extends Model
{
    protected $table = 'produto_unidade';

    public $timestamps = false;

    protected $fillable = [
        'produto_id',
        'unidade_id',
        'quantidade',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_id');
    }
}
