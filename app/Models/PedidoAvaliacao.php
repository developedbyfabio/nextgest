<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Controle de IDEMPOTÊNCIA do pedido de avaliação pós-serviço por WhatsApp (Fatia 5,
 * D81). Vive no banco do TENANT. Uma linha por agendamento (`agendamento_id` único):
 * garante "um pedido por atendimento" e alimenta o teto diário. A avaliação fica em
 * `avaliacoes` (D51); aqui só o envio do link.
 */
class PedidoAvaliacao extends Model
{
    protected $table = 'pedidos_avaliacao';

    public const ENFILEIRADO = 'enfileirado';

    public const ENVIADO = 'enviado';

    public const FALHOU = 'falhou';

    protected $fillable = [
        'agendamento_id',
        'status',
        'enfileirado_em',
        'agendado_para',
        'enviado_em',
    ];

    protected function casts(): array
    {
        return [
            'enfileirado_em' => 'datetime',
            // Represamento pela janela de horário (D83): enfileirado + agendado_para no
            // futuro = adiado; o comando re-despacha quando vence. Fuso APP_TIMEZONE.
            'agendado_para' => 'datetime',
            'enviado_em' => 'datetime',
        ];
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }
}
