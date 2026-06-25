<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Fatura mensal da assinatura SaaS (D58) — 1:N de Assinatura, na conexão central.
 * `valor` é SNAPSHOT da competência (não reescreve quando o preço muda).
 */
class Fatura extends Model
{
    use CentralConnection;

    protected $table = 'faturas';

    // Status da fatura.
    public const ABERTA = 'aberta';

    public const PAGA = 'paga';

    public const ATRASADA = 'atrasada';

    public const CANCELADA = 'cancelada';

    protected $fillable = [
        'assinatura_id',
        'competencia',
        'valor',
        'data_vencimento',
        'status',
        'data_pagamento',
        'forma_pagamento',
        'link_pagamento',
        'gateway_referencia',
    ];

    protected function casts(): array
    {
        return [
            'competencia' => 'date',
            'data_vencimento' => 'date',
            'data_pagamento' => 'date',
            'valor' => 'decimal:2',
        ];
    }

    public function assinatura(): BelongsTo
    {
        return $this->belongsTo(Assinatura::class, 'assinatura_id');
    }
}
