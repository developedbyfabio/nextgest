<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LOG de mensagens de WhatsApp ENVIADAS (Controle de mensagens, D83). Vive no banco do
 * TENANT. Uma linha por tentativa de envio (status terminal: enviado | falhou | descartado).
 *
 * Privacidade (LGPD): o `conteudo` é EXPURGADO automaticamente após o prazo
 * (config('whatsapp.historico.expurgo_dias')), mantendo os metadados. Registra ENVIO,
 * nunca recebimento, e NÃO se cruza com `avaliacoes` (anonimato D51 preservado: saber que
 * "pedi avaliação ao cliente X" ≠ saber "o que o cliente X avaliou"). Gated por
 * `gerenciar_whatsapp` nas telas.
 */
class MensagemWhatsapp extends Model
{
    protected $table = 'mensagens_whatsapp';

    public const ENVIADO = 'enviado';

    public const FALHOU = 'falhou';

    public const DESCARTADO = 'descartado';

    /** Automação "avulsa": envio manual 1 a 1 pela aba Clientes (D88). */
    public const AVULSO = 'avulso';

    protected $fillable = [
        'automacao',
        'agendamento_id',
        'cliente_id',
        'telefone',
        'status',
        'motivo',
        'conteudo',
        'conteudo_expurgado_em',
        'enviado_em',
    ];

    protected function casts(): array
    {
        return [
            'conteudo_expurgado_em' => 'datetime',
            'enviado_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** Conteúdo ainda não expurgado (para o comando de expurgo). */
    public function scopeComConteudo(Builder $query): Builder
    {
        return $query->whereNotNull('conteudo');
    }
}
