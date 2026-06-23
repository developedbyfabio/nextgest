<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento de uma assinatura (banco do TENANT). Histórico/auditoria das mudanças de
 * status — é a FONTE da evolução/churn dos indicadores ("novos no mês" = `criada`,
 * "cancelamentos no mês" = `cancelada`). Gravado a cada transição pelo serviço.
 */
class EventoAssinaturaClube extends Model
{
    protected $table = 'eventos_assinatura_clube';

    public const TIPO_CRIADA = 'criada';

    public const TIPO_RENOVADA = 'renovada';

    public const TIPO_PAGAMENTO_OK = 'pagamento_ok';

    public const TIPO_PAGAMENTO_FALHOU = 'pagamento_falhou';

    public const TIPO_CANCELADA = 'cancelada';

    public const TIPO_REATIVADA = 'reativada';

    protected $fillable = [
        'assinatura_id',
        'tipo',
        'ocorrido_em',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'ocorrido_em' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function assinatura(): BelongsTo
    {
        return $this->belongsTo(AssinaturaClube::class, 'assinatura_id');
    }
}
