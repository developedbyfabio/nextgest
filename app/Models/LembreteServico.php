<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Controle de IDEMPOTÊNCIA do lembrete de serviço por WhatsApp (Fatia 4, D79). Vive no
 * banco do TENANT. Uma linha por agendamento (`agendamento_id` único): o registro é a
 * garantia de "um lembrete por agendamento" e a base de contagem do teto diário.
 */
class LembreteServico extends Model
{
    protected $table = 'lembretes_servico';

    public const ENFILEIRADO = 'enfileirado';

    public const ENVIADO = 'enviado';

    public const FALHOU = 'falhou';

    protected $fillable = [
        'agendamento_id',
        'status',
        'enfileirado_em',
        'enviado_em',
    ];

    protected function casts(): array
    {
        return [
            'enfileirado_em' => 'datetime',
            'enviado_em' => 'datetime',
        ];
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }
}
