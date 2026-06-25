<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avaliação de um atendimento concluído (D51). Vive no banco do TENANT.
 * 1 atendimento (agendamento) = 1 avaliação. O cliente avalia o PRÓPRIO
 * atendimento (1–5 estrelas + comentário opcional). Serviço(s) derivam do
 * agendamento (itens). Visualização no painel (RBAC) é o Prompt 2.
 */
class Avaliacao extends Model
{
    // Plural irregular: o default do Eloquent seria "avaliacaos".
    protected $table = 'avaliacoes';

    protected $fillable = [
        'agendamento_id',
        'cliente_id',
        'profissional_id',
        'unidade_id',
        'nota',
        'comentario',
    ];

    protected function casts(): array
    {
        return [
            'nota' => 'integer',
        ];
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profissional_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }
}
