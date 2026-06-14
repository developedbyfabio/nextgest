<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Faixa de trabalho recorrente de um profissional numa unidade, num dia da
 * semana. Pode haver múltiplas faixas no mesmo dia (ex.: manhã e tarde, com
 * intervalo de almoço entre elas). Vive no banco do TENANT.
 */
class HorarioTrabalho extends Model
{
    protected $table = 'horarios_trabalho';

    protected $fillable = [
        'user_id',
        'unidade_id',
        'dia_semana',
        'hora_inicio',
        'hora_fim',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }
}
