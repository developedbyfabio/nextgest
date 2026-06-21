<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pagamento de uma venda/comanda (ou, no futuro, de uma mensalidade do clube).
 * Banco do TENANT.
 *
 * Etapa 1 (presencial): `gateway_id` nulo, `status = aprovado` na hora, `pago_em`
 * preenchido e `criado_por_user_id` = quem registrou. Os campos de gateway
 * (`gateway_transacao_id`, `pix_copia_cola`, `link_pagamento`) existem mas ficam
 * SEM USO — entram na etapa 2 (Mercado Pago).
 */
class Pagamento extends Model
{
    protected $table = 'pagamentos';

    /** Formas de pagamento presencial. */
    public const METODOS = ['dinheiro', 'cartao_debito', 'cartao_credito', 'pix', 'maquininha'];

    public const METODO_LABEL = [
        'dinheiro' => 'Dinheiro',
        'cartao_debito' => 'Cartão de débito',
        'cartao_credito' => 'Cartão de crédito',
        'pix' => 'Pix',
        'maquininha' => 'Maquininha',
    ];

    protected $fillable = [
        'venda_id',
        'assinatura_id',
        'gateway_id',
        'metodo',
        'valor',
        'status',
        'gateway_transacao_id',
        'pix_copia_cola',
        'link_pagamento',
        'pago_em',
        'criado_por_user_id',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'pago_em' => 'datetime',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }
}
