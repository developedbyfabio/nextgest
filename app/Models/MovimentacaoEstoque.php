<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimentação de estoque (banco do TENANT): cada entrada/saída/ajuste de um
 * produto numa filial. `quantidade` é o DELTA SINALIZADO aplicado ao estoque
 * (+entra / -sai). Histórico rastreável; o estoque atual da filial é a soma dos
 * deltas. `venda_id` é preenchido nas saídas por venda (Fatia 2B).
 */
class MovimentacaoEstoque extends Model
{
    protected $table = 'movimentacoes_estoque';

    // A tabela (migration 190004) tem só `created_at` (useCurrent), sem `updated_at`.
    const UPDATED_AT = null;

    protected $fillable = [
        'produto_id',
        'unidade_id',
        'tipo',
        'quantidade',
        'motivo',
        'user_id',
        'venda_id',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
            'created_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
