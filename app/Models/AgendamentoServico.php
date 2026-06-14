<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de um agendamento (serviço), com snapshot de preço e duração no momento.
 * Vive no banco do TENANT. Sem updated_at/created_at separados (tabela enxuta).
 */
class AgendamentoServico extends Model
{
    protected $table = 'agendamento_servico';

    public $timestamps = false;

    protected $fillable = [
        'agendamento_id',
        'servico_id',
        'preco',
        'duracao_minutos',
    ];

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'duracao_minutos' => 'integer',
        ];
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }
}
