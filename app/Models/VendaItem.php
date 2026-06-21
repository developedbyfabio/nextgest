<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de uma venda/comanda (banco do TENANT): é OU serviço OU produto. Guarda
 * SNAPSHOT de `descricao` e `preco_unitario` (o histórico não muda se o cadastro
 * mudar depois). Comissão (`percentual_comissao`/`valor_comissao`) é snapshot
 * gravado ao pagar. `subtotal = preco_unitario × quantidade`.
 */
class VendaItem extends Model
{
    protected $table = 'venda_itens';

    protected $fillable = [
        'venda_id',
        'tipo',
        'servico_id',
        'produto_id',
        'descricao',
        'quantidade',
        'preco_unitario',
        'subtotal',
        'profissional_id',
        'percentual_comissao',
        'valor_comissao',
        'coberto_por_assinatura',
        'assinatura_id',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
            'preco_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'percentual_comissao' => 'decimal:2',
            'valor_comissao' => 'decimal:2',
            'coberto_por_assinatura' => 'boolean',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profissional_id');
    }
}
